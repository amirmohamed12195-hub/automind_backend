<?php

namespace App\Enums;

enum DiagnosticStatus: string
{
    case Draft = 'draft';
    case Uploading = 'uploading';
    case Queued = 'queued';
    case Analyzing = 'analyzing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function mutable(): bool
    {
        return in_array($this, [self::Draft, self::Uploading], true);
    }
}
