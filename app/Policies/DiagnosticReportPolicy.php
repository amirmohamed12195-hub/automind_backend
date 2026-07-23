<?php

namespace App\Policies;

use App\Models\DiagnosticReport;
use App\Models\User;

class DiagnosticReportPolicy
{
    public function view(User $user, DiagnosticReport $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function update(User $user, DiagnosticReport $report): bool
    {
        return $report->user_id === $user->id;
    }
}
