<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestMessage extends UlidModel
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return BelongsTo<Mechanic, $this> */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }
}
