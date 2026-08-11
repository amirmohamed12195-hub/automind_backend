<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiagnosticSession extends UlidModel
{
    protected function casts(): array
    {
        return [
            'input_manifest' => 'array', 'started_at' => 'datetime', 'analyzed_at' => 'datetime',
            'completed_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'consented_at' => 'datetime', 'progress_percentage' => 'integer', 'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsToMany<SymptomDefinition, $this> */
    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(SymptomDefinition::class, 'diagnostic_session_symptoms')->withTimestamps();
    }

    /** @return HasMany<DiagnosticMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(DiagnosticMedia::class);
    }

    /** @return HasMany<ObdSnapshot, $this> */
    public function obdSnapshots(): HasMany
    {
        return $this->hasMany(ObdSnapshot::class);
    }

    /** @return HasMany<AiRun, $this> */
    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    /** @return HasOne<DiagnosticReport, $this> */
    public function report(): HasOne
    {
        return $this->hasOne(DiagnosticReport::class);
    }

    /** @return HasOne<ReportEntitlementReservation, $this> */
    public function entitlementReservation(): HasOne
    {
        return $this->hasOne(ReportEntitlementReservation::class);
    }
}
