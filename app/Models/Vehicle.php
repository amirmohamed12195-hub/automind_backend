<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends UlidModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['year' => 'integer', 'mileage_km' => 'integer', 'health_score' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<VehicleMake, $this> */
    public function catalogMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'catalog_make_id');
    }

    /** @return BelongsTo<VehicleModel, $this> */
    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'catalog_model_id');
    }

    /** @return HasMany<DiagnosticSession, $this> */
    public function diagnostics(): HasMany
    {
        return $this->hasMany(DiagnosticSession::class);
    }

    /** @return HasMany<VehicleMaintenanceRecord, $this> */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class);
    }

    /** @return HasMany<MaintenanceReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(MaintenanceReminder::class);
    }
}
