<?php

namespace App\Services;

use App\Models\AiRun;
use App\Models\Appointment;
use App\Models\DiagnosticSession;
use App\Models\Mechanic;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardMetrics
{
    /** @return array<string, mixed> */
    public function snapshot(int $days, bool $accountStatusAvailable, bool $loginActivityAvailable, bool $databaseAvailable = true): array
    {
        $end = now();
        $start = $end->copy()->subDays($days - 1)->startOfDay();
        $weekStart = $end->copy()->subDays(6)->startOfDay();
        $overview = array_fill_keys([
            'users', 'allAccounts', 'enabledUsers', 'activeUsers', 'administrators', 'deletedUsers', 'newUsers',
            'vehicles', 'diagnostics', 'diagnosticsToday', 'verifiedMechanics', 'pendingAppointments',
            'failedAiRuns', 'suspendedUsers', 'completedAiRuns', 'terminalAiRuns',
        ], 0) + ['aiSuccessRate' => null];
        $registrations = collect();
        $sessions = collect();
        $outcomes = collect();

        if ($databaseAvailable) {
            // Account totals never depend on the current page or search filters.
            $overview['users'] = User::query()->count();
            $overview['deletedUsers'] = User::onlyTrashed()->count();
            $overview['allAccounts'] = $overview['users'] + $overview['deletedUsers'];
            $overview['suspendedUsers'] = $accountStatusAvailable ? User::query()->whereNotNull('suspended_at')->count() : 0;
            $overview['enabledUsers'] = $overview['users'] - $overview['suspendedUsers'];
            $overview['administrators'] = User::query()->where('is_admin', true)->count();
            $overview['activeUsers'] = $loginActivityAvailable
                ? User::query()->when($accountStatusAvailable, fn ($query) => $query->whereNull('suspended_at'))
                    ->whereBetween('last_login_at', [$weekStart, $end])->count()
                : 0;
            $overview['newUsers'] = User::query()->whereBetween('created_at', [$start, $end])->count();
            $overview['vehicles'] = Vehicle::query()->count();
            $overview['diagnostics'] = DiagnosticSession::query()->count();
            $overview['diagnosticsToday'] = DiagnosticSession::query()->whereBetween('created_at', [$end->copy()->startOfDay(), $end])->count();
            $overview['verifiedMechanics'] = Mechanic::query()->where('verified', true)->where('active', true)->count();
            $overview['pendingAppointments'] = Appointment::query()->whereIn('status', ['requested', 'confirmed'])->count();
            $overview['failedAiRuns'] = AiRun::query()->where('status', 'failed')->count();

            $aiOutcomes = AiRun::query()->whereBetween('created_at', [$weekStart, $end])
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $overview['completedAiRuns'] = (int) $aiOutcomes->get('completed', 0);
            $overview['terminalAiRuns'] = $overview['completedAiRuns'] + (int) $aiOutcomes->get('failed', 0);
            $overview['aiSuccessRate'] = $overview['terminalAiRuns'] > 0
                ? round($overview['completedAiRuns'] / $overview['terminalAiRuns'] * 100, 1) : null;

            // DATE works on the supported SQLite and MySQL databases. Bounds and labels
            // use the application timezone, matching the stored application timestamps.
            $dailyCounts = fn (Builder $query) => $query->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
            $registrations = $dailyCounts(User::query());
            $sessions = $dailyCounts(DiagnosticSession::query());
            $outcomes = DiagnosticSession::query()->whereBetween('created_at', [$start, $end])
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        }

        $activity = collect(range($days - 1, 0))->map(function (int $daysAgo) use ($end, $registrations, $sessions): array {
            $day = $end->copy()->subDays($daysAgo);

            return [
                'date' => $day->toDateString(), 'label' => $day->format('M j'),
                'users' => (int) $registrations->get($day->toDateString(), 0),
                'diagnostics' => (int) $sessions->get($day->toDateString(), 0),
            ];
        });

        return [
            'overview' => $overview,
            'activity' => $activity,
            'diagnosticOutcomes' => collect([
                ['label' => 'Completed', 'color' => '#159b86', 'count' => (int) $outcomes->get('completed', 0)],
                ['label' => 'In progress', 'color' => '#3982f7', 'count' => (int) $outcomes->only(['uploading', 'queued', 'analyzing'])->sum()],
                ['label' => 'Draft', 'color' => '#a5b4c8', 'count' => (int) $outcomes->get('draft', 0)],
                ['label' => 'Failed', 'color' => '#ed7181', 'count' => (int) $outcomes->get('failed', 0)],
                ['label' => 'Cancelled', 'color' => '#e7af52', 'count' => (int) $outcomes->get('cancelled', 0)],
            ]),
            'analyticsDays' => $days,
            'analyticsStart' => $start,
            'analyticsEnd' => $end,
            'loginActivityAvailable' => $loginActivityAvailable,
        ];
    }
}
