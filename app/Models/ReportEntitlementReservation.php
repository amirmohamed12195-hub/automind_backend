<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEntitlementReservation extends UlidModel
{
    protected function casts(): array
    {
        return ['reserved_at' => 'datetime', 'finalized_at' => 'datetime', 'released_at' => 'datetime'];
    }

    /** @return BelongsTo<DiagnosticSession, $this> */
    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(DiagnosticSession::class, 'diagnostic_session_id');
    }
}
