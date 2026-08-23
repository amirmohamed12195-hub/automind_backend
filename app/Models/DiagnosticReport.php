<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiagnosticReport extends UlidModel
{
    protected function casts(): array
    {
        return ['overall_confidence' => 'float', 'professional_inspection_required' => 'boolean', 'limitations' => 'array', 'missing_evidence' => 'array', 'generated_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<DiagnosticSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DiagnosticSession::class, 'diagnostic_session_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return HasMany<DiagnosticReportTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(DiagnosticReportTranslation::class);
    }

    /** @return HasMany<SuspectedFault, $this> */
    public function faults(): HasMany
    {
        return $this->hasMany(SuspectedFault::class)->orderBy('sort_order');
    }

    /** @return HasMany<ReportAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ReportAction::class)->orderBy('sort_order');
    }

    /** @return HasMany<ReportEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(ReportEvidence::class);
    }

    /** @return HasOne<ServiceEstimate, $this> */
    public function estimate(): HasOne
    {
        return $this->hasOne(ServiceEstimate::class);
    }

    /** @return HasMany<PriceSearch, $this> */
    public function priceSearches(): HasMany
    {
        return $this->hasMany(PriceSearch::class);
    }

    /** @return HasMany<ReportFollowUp, $this> */
    public function followUps(): HasMany
    {
        return $this->hasMany(ReportFollowUp::class)->orderBy('created_at');
    }
}
