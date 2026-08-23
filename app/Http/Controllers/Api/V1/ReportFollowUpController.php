<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ObjectStorageProvider;
use App\Contracts\ReportAssistantProvider;
use App\Http\Resources\DiagnosticReportResource;
use App\Models\DiagnosticReport;
use App\Models\ReportFollowUp;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReportFollowUpController
{
    public function index(DiagnosticReport $report)
    {
        Gate::authorize('view', $report);

        return ApiResponse::success($report->followUps()->get()->map(fn (ReportFollowUp $item) => $this->resource($item))->all());
    }

    public function store(
        Request $request,
        DiagnosticReport $report,
        ObjectStorageProvider $storage,
        ReportAssistantProvider $assistant,
    ) {
        Gate::authorize('update', $report);
        $data = $request->validate([
            'question' => ['nullable', 'string', 'max:2000'],
            'photos' => ['sometimes', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ]);
        if (trim((string) ($data['question'] ?? '')) === '' && ! $request->hasFile('photos')) {
            throw ValidationException::withMessages(['question' => [__('api.follow_up_content_required')]]);
        }
        if ($report->followUps()->count() >= 30) {
            return ApiResponse::error('FOLLOW_UP_LIMIT_REACHED', __('api.follow_up_limit'), 409);
        }

        $attachments = [];
        try {
            foreach ($request->file('photos', []) as $photo) {
                $stored = $storage->storePrivate($photo, "report-follow-ups/{$report->id}");
                $attachments[] = [
                    'disk' => $stored['disk'], 'path' => $stored['path'],
                    'mimeType' => $stored['mimeType'], 'byteSize' => $stored['byteSize'],
                    'sha256' => $stored['sha256'],
                ];
            }
            $report->load($this->reportRelations());
            $context = (new DiagnosticReportResource($report))->resolve($request);
            $result = $assistant->answer(
                $context,
                trim((string) ($data['question'] ?? '')) ?: null,
                $attachments,
                hash_hmac('sha256', (string) $request->user()->id, (string) config('app.key')),
            );
            $answer = $result->data['answer'] ?? [];
            $followUp = ReportFollowUp::query()->create([
                'diagnostic_report_id' => $report->id,
                'user_id' => $request->user()->id,
                'question' => trim((string) ($data['question'] ?? '')) ?: null,
                'answer_en' => (string) ($answer['en'] ?? ''),
                'answer_ar' => (string) ($answer['ar'] ?? ''),
                'confidence' => $result->data['confidence'] ?? null,
                'professional_inspection_required' => (bool) ($result->data['professionalInspectionRequired'] ?? true),
                'suggested_evidence_json' => $result->data['suggestedEvidence'] ?? [],
                'attachments_json' => $attachments,
            ]);

            return ApiResponse::success($this->resource($followUp), 201);
        } catch (Throwable $error) {
            foreach ($attachments as $attachment) {
                $storage->delete($attachment['disk'], $attachment['path']);
            }
            throw $error;
        }
    }

    private function resource(ReportFollowUp $item): array
    {
        $locale = app()->getLocale();
        $suggested = collect($item->suggested_evidence_json ?? [])->map(
            fn ($value) => is_array($value) ? ($value[$locale] ?? $value['en'] ?? null) : $value,
        )->filter()->values()->all();

        return [
            'id' => (string) $item->id,
            'reportId' => (string) $item->diagnostic_report_id,
            'question' => $item->question,
            'answer' => $locale === 'ar' ? $item->answer_ar : $item->answer_en,
            'confidence' => $item->confidence,
            'professionalInspectionRequired' => (bool) $item->professional_inspection_required,
            'suggestedEvidence' => $suggested,
            'photoCount' => count($item->attachments_json ?? []),
            'createdAt' => $item->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private function reportRelations(): array
    {
        return ['vehicle', 'translations', 'faults.translations', 'faults.causes.translations', 'faults.actions.translations', 'faults.parts.translations', 'faults.evidence', 'actions.translations', 'evidence', 'estimate.lineItems', 'priceSearches.sources'];
    }
}
