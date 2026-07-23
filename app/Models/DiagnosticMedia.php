<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticMedia extends UlidModel
{
    protected $table = 'diagnostic_media';

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    /** @return BelongsTo<DiagnosticSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DiagnosticSession::class, 'diagnostic_session_id');
    }

    /** @return HasMany<MediaObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(MediaObservation::class);
    }
}
