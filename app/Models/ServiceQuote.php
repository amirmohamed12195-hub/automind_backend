<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceQuote extends UlidModel
{
    protected function casts(): array
    {
        return ['line_items_json' => 'array', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<Mechanic, $this> */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }
}
