<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends UlidModel
{
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

    /** @return BelongsTo<DiagnosticReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DiagnosticReport::class, 'diagnostic_report_id');
    }

    /** @return BelongsTo<ServiceQuote, $this> */
    public function selectedQuote(): BelongsTo
    {
        return $this->belongsTo(ServiceQuote::class, 'selected_quote_id');
    }

    /** @return BelongsToMany<Mechanic, $this> */
    public function mechanics(): BelongsToMany
    {
        return $this->belongsToMany(Mechanic::class, 'service_request_mechanic')
            ->withPivot('status')->withTimestamps();
    }

    /** @return HasMany<ServiceQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(ServiceQuote::class);
    }

    /** @return HasMany<ServiceRequestMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ServiceRequestMessage::class)->orderBy('created_at');
    }
}
