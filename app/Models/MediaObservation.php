<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaObservation extends UlidModel
{
    protected function casts(): array
    {
        return ['confidence' => 'float', 'reliability' => 'float', 'bounding_box_or_time_range' => 'array'];
    }

    /** @return BelongsTo<DiagnosticMedia, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(DiagnosticMedia::class, 'diagnostic_media_id');
    }

    /** @return BelongsTo<AiRun, $this> */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }
}
