<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceRequest */
class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $reportTranslation = $this->report?->translations->firstWhere('locale', $locale)
            ?? $this->report?->translations->firstWhere('locale', 'en');

        return [
            'id' => (string) $this->id,
            'vehicleId' => (string) $this->vehicle_id,
            'vehicleName' => $this->vehicle ? trim($this->vehicle->brand.' '.$this->vehicle->model) : null,
            'reportId' => $this->diagnostic_report_id,
            'reportTitle' => $reportTranslation?->title,
            'selectedQuoteId' => $this->selected_quote_id,
            'status' => $this->status,
            'description' => $this->description,
            'currency' => $this->currency,
            'mechanics' => $this->whenLoaded('mechanics', fn () => $this->mechanics->map(fn ($mechanic) => [
                'id' => (string) $mechanic->id,
                'name' => $locale === 'ar' ? $mechanic->name_ar : $mechanic->name_en,
                'status' => $mechanic->serviceRequestInvitationStatus(),
            ])->values()->all()),
            'quotes' => $this->whenLoaded('quotes', fn () => $this->quotes->map(fn ($quote) => [
                'id' => (string) $quote->id,
                'mechanicId' => (string) $quote->mechanic_id,
                'mechanicName' => $locale === 'ar' ? $quote->mechanic->name_ar : $quote->mechanic->name_en,
                'status' => $quote->status,
                'currency' => $quote->currency,
                'laborAmount' => $quote->labor_amount,
                'partsAmount' => $quote->parts_amount,
                'feesAmount' => $quote->fees_amount,
                'totalAmount' => $quote->total_amount,
                'estimatedDurationMinutes' => $quote->estimated_duration_minutes,
                'warrantyText' => $quote->warranty_text,
                'notes' => $quote->notes,
                'lineItems' => $quote->line_items_json ?? [],
                'expiresAt' => $quote->expires_at?->utc()->toIso8601ZuluString(),
                'createdAt' => $quote->created_at?->utc()->toIso8601ZuluString(),
            ])->values()->all()),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message) => [
                'id' => (string) $message->id,
                'senderRole' => $message->sender_role,
                'mechanicId' => $message->mechanic_id,
                'mechanicName' => $message->mechanic
                    ? ($locale === 'ar' ? $message->mechanic->name_ar : $message->mechanic->name_en)
                    : null,
                'body' => $message->body,
                'createdAt' => $message->created_at?->utc()->toIso8601ZuluString(),
            ])->values()->all()),
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
