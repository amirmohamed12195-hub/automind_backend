<?php

namespace App\Models;

class ReportFeedback extends UlidModel
{
    protected $table = 'report_feedback';

    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }
}
