<?php

namespace Tests\Feature;

use App\Contracts\AiDiagnosticProvider;
use App\Contracts\AudioUnderstandingProvider;
use App\Contracts\SpeechTranscriptionProvider;
use App\Contracts\VisionUnderstandingProvider;
use App\Contracts\WebPriceSearchProvider;
use App\Jobs\AnalyzeDiagnosticSession;
use App\Jobs\ProcessDiagnosticMedia;
use App\Jobs\RefreshServiceEstimate;
use App\Models\DiagnosticMedia;
use App\Models\DiagnosticSession;
use App\Models\SymptomDefinition;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Services\Diagnostics\DiagnosticReportPersister;
use App\Services\Media\MediaToolchain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeAiProviders;

class DiagnosisApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Diagnosis contract tests exercise the pipeline itself. Billing behavior
        // has dedicated coverage in BillingApiTest and must not depend on a
        // developer's local BILLING_ENABLED value.
        config(['billing.enabled' => false]);
        foreach ([['engine', 'Engine', 'المحرك'], ['performance', 'Performance', 'الأداء']] as $i => [$code, $en, $ar]) {
            SymptomDefinition::query()->create(['code' => $code, 'label_en' => $en, 'label_ar' => $ar, 'sort_order' => $i]);
        }
    }

    public function test_create_update_obd_analyze_poll_and_cancel_flow_is_idempotent(): void
    {
        Queue::fake();
        $user = $this->actingAsUser(['country_code' => 'EG', 'city' => 'Cairo', 'currency' => 'EGP']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $payload = ['vehicleId' => $vehicle->id, 'description' => 'Engine shakes at idle.', 'selectedSymptoms' => ['engine', 'performance'], 'inputLocale' => 'en', 'reportLocale' => 'en', 'market' => ['countryCode' => 'EG', 'city' => 'Cairo', 'currency' => 'EGP'], 'consentVersion' => 'privacy-v1'];
        $created = $this->withHeader('Idempotency-Key', 'diagnosis-1')->postJson('/api/v1/diagnoses', $payload)->assertCreated()->assertJsonPath('data.status', 'draft');
        $id = $created->json('data.id');
        $this->withHeader('Idempotency-Key', 'diagnosis-1')->postJson('/api/v1/diagnoses', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson("/api/v1/diagnoses/$id/obd-snapshots", ['recordedAt' => now()->toIso8601String(), 'troubleCodes' => ['p0301'], 'rpm' => 815, 'speed' => 0, 'coolantTemperature' => 194, 'batteryVoltage' => 13.8, 'fuelTrim' => 5.3, 'engineLoad' => 24, 'units' => ['speed' => 'km/h', 'coolantTemperature' => 'fahrenheit']])->assertCreated()->assertJsonPath('data.troubleCodes.0', 'P0301')->assertJsonPath('data.coolantCelsius', 90);

        $this->postJson("/api/v1/diagnoses/$id/obd-snapshots", ['recordedAt' => now()->toIso8601String(), 'speed' => 311, 'units' => ['speed' => 'mph']])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['speed']]]);
        $this->postJson("/api/v1/diagnoses/$id/analyze")->assertStatus(202)->assertJsonPath('data.status', 'queued')->assertJsonPath('data.currentStep', 'preparingData');
        Queue::assertPushed(AnalyzeDiagnosticSession::class);
        $this->getJson("/api/v1/diagnoses/$id/status")->assertOk()->assertJsonPath('data.status', 'queued');
        $this->postJson("/api/v1/diagnoses/$id/analyze")->assertStatus(202);
        Queue::assertPushed(AnalyzeDiagnosticSession::class, 1);
        DiagnosticSession::query()->whereKey($id)->update(['updated_at' => now()->subMinutes(2)]);
        $this->postJson("/api/v1/diagnoses/$id/analyze")->assertStatus(202);
        Queue::assertPushed(AnalyzeDiagnosticSession::class, 2);
        $this->postJson("/api/v1/diagnoses/$id/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_diagnosis_requires_evidence_and_enforces_ownership(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $this->postJson('/api/v1/diagnoses', ['vehicleId' => $vehicle->id, 'description' => '', 'selectedSymptoms' => [], 'inputLocale' => 'en', 'reportLocale' => 'en', 'consentVersion' => 'privacy-v1'])->assertUnprocessable();
        $otherVehicle = Vehicle::factory()->create();
        $this->postJson('/api/v1/diagnoses', ['vehicleId' => $otherVehicle->id, 'description' => 'Noise', 'selectedSymptoms' => [], 'inputLocale' => 'en', 'reportLocale' => 'en', 'consentVersion' => 'privacy-v1'])->assertNotFound();

        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'description' => 'Existing evidence']);
        $this->patchJson("/api/v1/diagnoses/$session->id", ['description' => '   ', 'selectedSymptoms' => []])->assertUnprocessable()->assertJsonPath('error.code', 'EVIDENCE_REQUIRED');
        $this->assertSame('Existing evidence', $session->fresh()->description);
    }

    public function test_media_uses_content_mime_limits_duplicates_and_processing_gate(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        for ($i = 1; $i <= 6; $i++) {
            $this->post("/api/v1/diagnoses/$session->id/media", ['kind' => 'photo', 'file' => UploadedFile::fake()->image("photo-$i.jpg", 100 + $i, 100 + $i)], ['Accept' => 'application/json'])->assertCreated();
        }
        Queue::assertPushed(ProcessDiagnosticMedia::class, 6);
        $this->post("/api/v1/diagnoses/$session->id/media", ['kind' => 'photo', 'file' => UploadedFile::fake()->image('seventh.jpg', 300, 300)], ['Accept' => 'application/json'])->assertStatus(409)->assertJsonPath('error.code', 'MEDIA_LIMIT_REACHED');
        $this->postJson("/api/v1/diagnoses/$session->id/analyze")->assertStatus(409)->assertJsonPath('error.code', 'MEDIA_NOT_READY');
        $other = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $this->post("/api/v1/diagnoses/$other->id/media", ['kind' => 'photo', 'file' => UploadedFile::fake()->create('spoof.jpg', 10, 'text/plain')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA');
    }

    public function test_media_rejects_oversize_and_duplicate_content_sanitizes_names_and_deletes_owned_file(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['automind.media.max_image_bytes' => 100]);
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $large = UploadedFile::fake()->image('large.jpg', 300, 300);
        $this->post("/api/v1/diagnoses/$session->id/media", ['kind' => 'photo', 'file' => $large], ['Accept' => 'application/json'])->assertUnprocessable();

        config(['automind.media.max_image_bytes' => 10485760]);
        $file = UploadedFile::fake()->image('../../evil.php.jpg', 80, 80);
        $created = $this->post("/api/v1/diagnoses/$session->id/media", ['kind' => 'photo', 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
        $media = DiagnosticMedia::query()->findOrFail($created->json('data.id'));
        $this->assertSame('evil.php.jpg', $media->original_filename);
        $this->assertStringNotContainsString('evil.php.jpg', $media->storage_path);
        Storage::disk('local')->assertExists($media->storage_path);

        $duplicate = new UploadedFile($file->getRealPath(), 'renamed.jpg', 'image/jpeg', null, true);
        $this->post("/api/v1/diagnoses/$session->id/media", ['kind' => 'photo', 'file' => $duplicate], ['Accept' => 'application/json'])->assertStatus(409)->assertJsonPath('error.code', 'DUPLICATE_MEDIA');
        $otherSession = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $this->deleteJson("/api/v1/diagnoses/$otherSession->id/media/$media->id")->assertNotFound();
        $this->deleteJson("/api/v1/diagnoses/$session->id/media/$media->id")->assertNoContent();
        Storage::disk('local')->assertMissing($media->storage_path);
        $this->assertNotNull($media->fresh()->deleted_at);
    }

    public function test_queued_job_persists_bilingual_report_and_flutter_contract(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'queued']);
        $fake = new FakeAiProviders;
        foreach ([AiDiagnosticProvider::class, AudioUnderstandingProvider::class, SpeechTranscriptionProvider::class, VisionUnderstandingProvider::class, WebPriceSearchProvider::class] as $contract) {
            $this->app->instance($contract, $fake);
        }
        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);
        $this->assertSame('completed', $session->fresh()->status);
        $report = $session->fresh()->report;
        $this->assertNotNull($report);
        $this->assertCount(2, $report->translations);
        $recommendedAction = $report->actions()->where('action_type', 'recommended_action')->firstOrFail();
        $this->assertDatabaseHas('maintenance_reminders', [
            'vehicle_id' => $vehicle->id,
            'source_report_id' => $report->id,
            'source_report_action_id' => $recommendedAction->id,
        ]);
        $this->getJson("/api/v1/reports/$report->id")->assertOk()->assertJsonStructure(['data' => ['id', 'sessionId', 'vehicleId', 'vehicleName', 'title', 'summary', 'confidence', 'severity', 'drivingRecommendation', 'suspectedFaults', 'safeChecks', 'recommendedActions', 'createdAt']])->assertJsonPath('data.severity', 'high');
        $this->withHeader('Accept-Language', 'ar')->getJson("/api/v1/reports/$report->id")->assertOk()->assertJsonPath('data.title', 'احتمال اختلال احتراق إحدى الأسطوانات')->assertJsonPath('data.severity', 'high');

        Queue::fake();
        $first = $this->withHeader('Idempotency-Key', 'estimate-refresh-1')->postJson("/api/v1/reports/$report->id/refresh-estimate")->assertStatus(202);
        $this->withHeader('Idempotency-Key', 'estimate-refresh-1')->postJson("/api/v1/reports/$report->id/refresh-estimate")->assertStatus(202)->assertJsonPath('data.priceSearchId', $first->json('data.priceSearchId'));
        Queue::assertPushed(RefreshServiceEstimate::class, 1);
    }

    public function test_report_persister_bounds_long_fault_cause_codes_without_losing_text(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $sharedPrefix = str_repeat('Engine oil level and condition require professional inspection because ', 3);
        $firstCause = $sharedPrefix.'the first possible cause must be verified.';
        $secondCause = $sharedPrefix.'the second possible cause must be verified.';
        $structured = FakeAiProviders::report();
        $structured['suspectedFaults'][0]['possibleCauses'] = [
            ['en' => $firstCause, 'ar' => 'السبب المحتمل الأول يحتاج إلى التحقق.'],
            ['en' => $secondCause, 'ar' => 'السبب المحتمل الثاني يحتاج إلى التحقق.'],
        ];

        $report = app(DiagnosticReportPersister::class)->persist($session, $structured);
        $causes = $report->faults()->firstOrFail()->causes()->orderBy('sort_order')->get();

        $this->assertCount(2, $causes);
        $this->assertLessThanOrEqual(120, strlen($causes[0]->canonical_code));
        $this->assertLessThanOrEqual(120, strlen($causes[1]->canonical_code));
        $this->assertNotSame($causes[0]->canonical_code, $causes[1]->canonical_code);
        $this->assertSame($firstCause, $causes[0]->translations()->where('locale', 'en')->value('text'));
        $this->assertSame($secondCause, $causes[1]->translations()->where('locale', 'en')->value('text'));
    }

    public function test_stale_job_cannot_complete_cancelled_session(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'cancelled']);
        $fake = new FakeAiProviders;
        foreach ([AiDiagnosticProvider::class, AudioUnderstandingProvider::class, SpeechTranscriptionProvider::class, VisionUnderstandingProvider::class, WebPriceSearchProvider::class] as $contract) {
            $this->app->instance($contract, $fake);
        }
        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertNull($session->fresh()->report);
    }

    public function test_retry_resumes_an_already_persisted_report_without_calling_provider_twice(): void
    {
        Queue::fake();
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'analyzing']);
        $report = app(DiagnosticReportPersister::class)->persist($session, FakeAiProviders::report());
        $fake = new FakeAiProviders;
        foreach ([AiDiagnosticProvider::class, AudioUnderstandingProvider::class, SpeechTranscriptionProvider::class, VisionUnderstandingProvider::class, WebPriceSearchProvider::class] as $contract) {
            $this->app->instance($contract, $fake);
        }

        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame($report->id, $session->fresh()->report?->id);
        $this->assertSame([], $fake->calls);
        $this->assertSame(1, UserNotification::query()->where('type', 'diagnosis_completed')->count());
    }

    public function test_audio_and_photo_stages_persist_observations_linked_to_ai_runs(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'queued']);
        foreach ([['engine_sound', 'audio/wav', 'wav'], ['photo', 'image/jpeg', 'jpg'], ['spoken_description', 'audio/wav', 'wav']] as $index => [$kind, $mime, $extension]) {
            DiagnosticMedia::query()->create([
                'diagnostic_session_id' => $session->id, 'media_kind' => $kind, 'storage_disk' => 'local',
                'storage_path' => "fixtures/$index.$extension", 'original_filename' => "fixture.$extension", 'mime_type' => $mime,
                'extension' => $extension, 'byte_size' => 100, 'sha256' => hash('sha256', "fixture-$index"),
                'upload_status' => 'uploaded', 'scan_status' => 'clean', 'processing_status' => 'ready',
            ]);
        }
        $fake = new FakeAiProviders;
        foreach ([AiDiagnosticProvider::class, AudioUnderstandingProvider::class, SpeechTranscriptionProvider::class, VisionUnderstandingProvider::class, WebPriceSearchProvider::class] as $contract) {
            $this->app->instance($contract, $fake);
        }

        $this->app->call([new AnalyzeDiagnosticSession($session->id), 'handle']);

        $this->assertDatabaseCount('media_observations', 3);
        $this->assertDatabaseHas('media_observations', ['observation_type' => 'engine_audio', 'canonical_code' => 'rhythmic_variation']);
        $this->assertDatabaseHas('media_observations', ['observation_type' => 'photo', 'canonical_code' => 'visible_surface']);
        $this->assertDatabaseHas('media_observations', ['observation_type' => 'transcription', 'canonical_code' => 'spoken_transcript']);
        $this->assertSame(1, $session->fresh()->media()->where('media_kind', 'photo')->firstOrFail()->observations()->count());
    }

    public function test_text_engine_audio_and_photo_complete_the_full_analysis_pipeline(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->app->instance(MediaToolchain::class, new class extends MediaToolchain
        {
            public function ffmpeg(): string
            {
                throw new \RuntimeException('FFmpeg is intentionally unavailable in this test.');
            }

            public function ffprobe(): string
            {
                throw new \RuntimeException('FFprobe is intentionally unavailable in this test.');
            }

            public function assertAvailable(): void
            {
                throw new \RuntimeException('Media tools are intentionally unavailable in this test.');
            }
        });
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $description = 'The engine shakes at idle and makes a repeating tapping sound.';

        $created = $this->withHeader('Idempotency-Key', 'multimodal-diagnosis-1')->postJson('/api/v1/diagnoses', [
            'vehicleId' => $vehicle->id,
            'description' => $description,
            'selectedSymptoms' => ['engine', 'performance'],
            'inputLocale' => 'en',
            'reportLocale' => 'en',
            'consentVersion' => 'privacy-v1',
        ])->assertCreated();
        $sessionId = $created->json('data.id');

        $photo = $this->post("/api/v1/diagnoses/$sessionId/media", [
            'kind' => 'photo',
            'file' => UploadedFile::fake()->image('engine-bay.jpg', 320, 240),
        ], ['Accept' => 'application/json'])->assertCreated();
        $audio = $this->post("/api/v1/diagnoses/$sessionId/media", [
            'kind' => 'engine_sound',
            'file' => UploadedFile::fake()->createWithContent('engine.wav', $this->wavFixture()),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'engine_sound')
            ->assertJsonPath('data.durationMilliseconds', 1000);

        foreach ([$photo->json('data.id'), $audio->json('data.id')] as $mediaId) {
            $this->app->call([new ProcessDiagnosticMedia($mediaId), 'handle']);
        }

        $this->assertDatabaseHas('diagnostic_media', [
            'id' => $audio->json('data.id'),
            'media_kind' => 'engine_sound',
            'mime_type' => 'audio/wav',
            'processing_status' => 'ready',
            'sample_rate' => 16000,
            'channels' => 1,
        ]);
        $this->assertDatabaseHas('diagnostic_media', [
            'id' => $photo->json('data.id'),
            'media_kind' => 'photo',
            'processing_status' => 'ready',
        ]);

        $fake = new FakeAiProviders;
        foreach ([AiDiagnosticProvider::class, AudioUnderstandingProvider::class, SpeechTranscriptionProvider::class, VisionUnderstandingProvider::class, WebPriceSearchProvider::class] as $contract) {
            $this->app->instance($contract, $fake);
        }

        $this->postJson("/api/v1/diagnoses/$sessionId/analyze")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');
        $this->app->call([new AnalyzeDiagnosticSession($sessionId), 'handle']);

        $session = DiagnosticSession::query()->findOrFail($sessionId);
        $this->assertSame('completed', $session->status);
        $this->assertSame(['audio', 'vision', 'synthesize'], array_column($fake->calls, 0));
        $synthesis = collect($fake->calls)->first(fn (array $call): bool => $call[0] === 'synthesize');
        $manifest = $synthesis[1];
        $this->assertSame($description, data_get($manifest, 'untrustedEvidence.description'));
        $this->assertSame('rhythmic_variation', data_get($manifest, 'untrustedEvidence.engineSoundObservations.observations.0.code'));
        $this->assertSame('visible_surface', data_get($manifest, 'untrustedEvidence.photoObservations.observations.0.code'));
        $this->assertDatabaseHas('media_observations', ['observation_type' => 'engine_audio', 'canonical_code' => 'rhythmic_variation']);
        $this->assertDatabaseHas('media_observations', ['observation_type' => 'photo', 'canonical_code' => 'visible_surface']);
        $this->getJson("/api/v1/diagnoses/$sessionId/report")
            ->assertOk()
            ->assertJsonPath('data.sessionId', $sessionId);
    }

    private function wavFixture(): string
    {
        $sampleRate = 16000;
        $samples = '';
        for ($sample = 0; $sample < $sampleRate; $sample++) {
            $value = (int) round(sin(2 * M_PI * 220 * $sample / $sampleRate) * 8000);
            $samples .= pack('v', $value & 0xFFFF);
        }

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;
    }
}
