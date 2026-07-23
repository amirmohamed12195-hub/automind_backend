<?php

namespace App\Jobs;

use App\Contracts\AiDiagnosticProvider;
use App\Contracts\AudioUnderstandingProvider;
use App\Contracts\SpeechTranscriptionProvider;
use App\Contracts\VisionUnderstandingProvider;
use App\DTO\AiProviderResult;
use App\Enums\DiagnosticStatus;
use App\Enums\DiagnosticStep;
use App\Exceptions\AiProviderException;
use App\Models\AuditLog;
use App\Models\DiagnosticMedia;
use App\Models\DiagnosticSession;
use App\Models\MediaObservation;
use App\Models\UserNotification;
use App\Services\Ai\AiRunRecorder;
use App\Services\Diagnostics\DiagnosticManifestBuilder;
use App\Services\Diagnostics\DiagnosticReportPersister;
use App\Services\Diagnostics\DiagnosticReportValidator;
use App\Services\Diagnostics\DiagnosticSafetyPolicy;
use App\Services\Diagnostics\DiagnosticStateMachine;
use App\Services\Notifications\UserNotificationService;
use App\Services\Pricing\PriceResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeDiagnosticSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $sessionId)
    {
        $this->onQueue('diagnostic-ai');
    }

    public function backoff(): array
    {
        return [random_int(25, 35), random_int(105, 135), random_int(540, 660)];
    }

    public function handle(
        DiagnosticStateMachine $stateMachine,
        DiagnosticManifestBuilder $manifestBuilder,
        SpeechTranscriptionProvider $speech,
        AudioUnderstandingProvider $audio,
        VisionUnderstandingProvider $vision,
        AiDiagnosticProvider $diagnostic,
        DiagnosticReportValidator $validator,
        DiagnosticSafetyPolicy $safety,
        DiagnosticReportPersister $persister,
        PriceResearchService $priceResearch,
        AiRunRecorder $runs,
        UserNotificationService $notifications,
    ): void {
        $lock = Cache::lock("diagnostic:{$this->sessionId}", $this->timeout + 30);
        if (! $lock->get()) {
            $this->release(10);

            return;
        }
        try {
            $session = DiagnosticSession::query()->with(['media', 'symptoms', 'obdSnapshots.troubleCodes', 'vehicle.maintenanceRecords'])->findOrFail($this->sessionId);
            if (in_array($session->status, ['completed', 'cancelled'], true)) {
                return;
            }
            if ($session->status === 'queued') {
                Log::info('diagnostic_queue_started', ['session_id' => $session->id, 'queue' => 'diagnostic-ai', 'queue_delay_ms' => max(0, now()->diffInMilliseconds($session->updated_at, true))]);
                $session = $stateMachine->transition($session, DiagnosticStatus::Analyzing, ['started_at' => $session->started_at ?? now(), 'current_step' => DiagnosticStep::PreparingData->value, 'progress_percentage' => 5, 'error_code' => null, 'safe_error_message' => null]);
            }
            if ($session->status !== 'analyzing') {
                return;
            }

            $manifest = $session->input_hash && is_array($session->input_manifest) ? $session->input_manifest : $manifestBuilder->build($session);
            $inputHash = $session->input_hash ?: hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $session->update(['input_manifest' => $manifest, 'input_hash' => $inputHash]);
            $safetyId = hash_hmac('sha256', (string) $session->user_id, (string) config('app.key'));
            $activeMedia = $session->media->whereNull('deleted_at');

            if (($spoken = $activeMedia->firstWhere('media_kind', 'spoken_description')) && empty($manifest['untrustedEvidence']['spokenDescription'])) {
                $this->checkpoint($session, DiagnosticStep::AnalyzingDescription, 15);
                $result = $runs->record($session, 'speech_transcription', $this->attempts(), fn () => $speech->transcribe($spoken->storage_disk, $spoken->storage_path, $spoken->mime_type, $session->input_locale));
                $manifest['untrustedEvidence']['spokenDescription'] = ['text' => $result->data['text'], 'language' => $result->data['language'] ?? null, 'sourceMediaId' => (string) $spoken->id];
                $language = $result->data['language'] ?? $session->input_locale;
                $this->persistObservations($spoken, $result, 'transcription', [[
                    'code' => 'spoken_transcript', 'confidence' => is_numeric($result->data['confidence'] ?? null) ? (float) $result->data['confidence'] : 0.0,
                    'textEn' => $language === 'en' ? (string) $result->data['text'] : '',
                    'textAr' => $language === 'ar' ? (string) $result->data['text'] : '',
                ]]);
                $session->update(['input_manifest' => $manifest]);
            }
            $this->assertNotCancelled($session);

            if (($engineSound = $activeMedia->firstWhere('media_kind', 'engine_sound')) && empty($manifest['untrustedEvidence']['engineSoundObservations'])) {
                $this->checkpoint($session, DiagnosticStep::AnalyzingSound, 30);
                $result = $runs->record($session, 'audio_understanding', $this->attempts(), fn () => $audio->understand($engineSound->storage_disk, $engineSound->storage_path, $engineSound->mime_type, $safetyId));
                $audioData = $result->data;
                $confidenceCap = match ($audioData['quality'] ?? 'poor') {
                    'strong' => 0.75, 'moderate' => 0.65, 'limited' => 0.50, default => 0.35,
                };
                $audioData['observations'] = collect($audioData['observations'] ?? [])->map(function ($observation) use ($confidenceCap) {
                    if (is_array($observation) && is_numeric($observation['confidence'] ?? null)) {
                        $observation['confidence'] = min($confidenceCap, max(0, (float) $observation['confidence']));
                    }

                    return $observation;
                })->all();
                $manifest['untrustedEvidence']['engineSoundObservations'] = ['sourceMediaId' => (string) $engineSound->id, ...$audioData];
                $this->persistObservations($engineSound, $result, 'engine_audio', $audioData['observations']);
                $session->update(['input_manifest' => $manifest]);
            }
            $this->assertNotCancelled($session);

            $photos = $activeMedia->where('media_kind', 'photo');
            if ($photos->isNotEmpty() && empty($manifest['untrustedEvidence']['photoObservations'])) {
                $this->checkpoint($session, DiagnosticStep::AnalyzingPhotos, 45);
                $result = $runs->record($session, 'vision', $this->attempts(), fn () => $vision->observe($photos->map(fn ($m) => ['id' => (string) $m->id, 'disk' => $m->storage_disk, 'path' => $m->storage_path, 'mimeType' => $m->mime_type])->values()->all(), $safetyId));
                $manifest['untrustedEvidence']['photoObservations'] = $result->data;
                foreach ($photos as $photo) {
                    $observations = collect($result->data['observations'] ?? [])->where('sourceMediaId', (string) $photo->id)->map(fn (array $observation) => [
                        'code' => $observation['code'] ?? null, 'confidence' => $observation['confidence'] ?? 0,
                        'textEn' => data_get($observation, 'text.en', ''), 'textAr' => data_get($observation, 'text.ar', ''),
                        'boundingBox' => $observation['boundingBox'] ?? null,
                    ])->all();
                    $this->persistObservations($photo, $result, 'photo', $observations);
                }
                $session->update(['input_manifest' => $manifest]);
            }
            if ($session->obdSnapshots->isNotEmpty()) {
                $this->checkpoint($session, DiagnosticStep::ReadingObd, 55);
            }
            $this->assertNotCancelled($session);

            if ($existingReport = $session->report()->first()) {
                if ($existingReport->faults()->whereHas('parts')->exists() && ! $existingReport->estimate()->exists()) {
                    RefreshServiceEstimate::dispatch($existingReport->id)->afterCommit();
                }
                $this->publish($stateMachine, $session, $existingReport->id, $notifications);

                return;
            }

            $this->checkpoint($session, DiagnosticStep::BuildingReport, 65);
            $synthesis = $runs->record($session, 'diagnostic_synthesis', $this->attempts(), fn () => $diagnostic->synthesize($manifest, $safetyId));
            $reportData = $validator->validate($synthesis->data);
            $reportData = $safety->enforce($reportData, $manifest);
            $quarantined = $reportData['_safety']['quarantinedActions'] ?? [];
            unset($reportData['_safety']);
            if ($reportData['recommendedActions'] === []) {
                $reportData['recommendedActions'][] = ['code' => 'professional_inspection', 'text' => ['en' => 'Arrange an inspection by a qualified automotive technician.', 'ar' => 'رتّب فحصاً لدى فني سيارات مؤهل.'], 'priority' => 1, 'professionalRequired' => true];
            }
            $validator->validate($reportData);
            $this->assertNotCancelled($session);
            $report = $persister->persist($session, $reportData);

            if ($quarantined !== []) {
                AuditLog::query()->create(['actor_user_id' => $session->user_id, 'action' => 'diagnostic.unsafe_actions_quarantined', 'target_type' => DiagnosticSession::class, 'target_id' => $session->id, 'request_id' => 'queue-'.$this->job?->getJobId(), 'metadata_json' => ['count' => count($quarantined)]]);
            }
            if (collect($reportData['suspectedFaults'])->flatMap(fn ($fault) => $fault['recommendedParts'])->isNotEmpty()) {
                $this->checkpoint($session, DiagnosticStep::ResearchingPrices, 82);
                $priceResearch->research($report, $reportData, $safetyId);
            }
            $this->assertNotCancelled($session);
            $this->publish($stateMachine, $session, $report->id, $notifications);
        } catch (AiProviderException $e) {
            if ($e->transient) {
                if ($e->retryAfterSeconds) {
                    $this->release($e->retryAfterSeconds);

                    return;
                }
                throw $e;
            }
            $this->markFailed($e->category, $e->getMessage());
        } catch (\LogicException $e) {
            if (DiagnosticSession::query()->whereKey($this->sessionId)->value('status') !== 'cancelled') {
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('retry_exhausted', 'The diagnostic service is temporarily unavailable. Please retry later.');
    }

    private function checkpoint(DiagnosticSession $session, DiagnosticStep $step, int $progress): void
    {
        $this->assertNotCancelled($session);
        $session->update(['current_step' => $step->value, 'progress_percentage' => $progress]);
    }

    private function assertNotCancelled(DiagnosticSession $session): void
    {
        if ($session->fresh()->status === 'cancelled') {
            throw new \LogicException('Diagnostic session was cancelled.');
        }
    }

    private function markFailed(string $code, string $message): void
    {
        DiagnosticSession::query()->whereKey($this->sessionId)->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'failed', 'current_step' => DiagnosticStep::Failed->value, 'error_code' => $code, 'safe_error_message' => mb_substr($message, 0, 1000), 'failed_at' => now(), 'updated_at' => now()]);
        Log::warning('Diagnostic analysis failed', ['session_id' => $this->sessionId, 'error_category' => $code]);
    }

    private function publish(DiagnosticStateMachine $stateMachine, DiagnosticSession $session, string $reportId, UserNotificationService $notifications): void
    {
        DB::transaction(function () use ($stateMachine, $session, $reportId, $notifications): void {
            $stateMachine->transition($session->fresh(), DiagnosticStatus::Completed, ['progress_percentage' => 100, 'current_step' => DiagnosticStep::Completed->value, 'analyzed_at' => now(), 'completed_at' => now()]);
            if (! UserNotification::query()->where('user_id', $session->user_id)->where('type', 'diagnosis_completed')->where('data_json->reportId', $reportId)->exists()) {
                $notifications->send(
                    $session->user()->firstOrFail(),
                    'diagnosis_completed',
                    'Your diagnostic report is ready',
                    'تقرير التشخيص جاهز',
                    'Open AutoMind to review the report safely.',
                    'افتح AutoMind لمراجعة التقرير بأمان.',
                    ['reportId' => $reportId],
                );
            }
        });
    }

    private function persistObservations(DiagnosticMedia $media, AiProviderResult $result, string $type, array $observations): void
    {
        $runId = $result->metadata['aiRunId'] ?? null;
        if (! is_string($runId) || $runId === '') {
            return;
        }
        DB::transaction(function () use ($media, $runId, $type, $observations): void {
            MediaObservation::query()->where('diagnostic_media_id', $media->id)->where('observation_type', $type)->delete();
            foreach ($observations as $observation) {
                if (! is_array($observation)) {
                    continue;
                }
                $textEn = trim((string) ($observation['textEn'] ?? ''));
                $textAr = trim((string) ($observation['textAr'] ?? ''));
                if ($textEn === '' && $textAr === '') {
                    continue;
                }
                $confidence = max(0, min(1, (float) ($observation['confidence'] ?? 0)));
                MediaObservation::query()->create([
                    'diagnostic_media_id' => $media->id, 'ai_run_id' => $runId, 'observation_type' => $type,
                    'canonical_code' => isset($observation['code']) ? mb_substr((string) $observation['code'], 0, 100) : null,
                    'confidence' => $confidence, 'reliability' => min($confidence, $type === 'engine_audio' ? 0.65 : 0.85),
                    'text_en' => $textEn, 'text_ar' => $textAr, 'bounding_box_or_time_range' => $observation['boundingBox'] ?? null,
                ]);
            }
        });
    }
}
