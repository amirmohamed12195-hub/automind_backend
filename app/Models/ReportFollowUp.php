<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFollowUp extends UlidModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'professional_inspection_required' => 'boolean',
            'suggested_evidence_json' => 'array',
            'attachments_json' => 'array',
        ];
    }

    /** @return BelongsTo<DiagnosticReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DiagnosticReport::class, 'diagnostic_report_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
