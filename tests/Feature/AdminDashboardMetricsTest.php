<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\DiagnosticSession;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(12, 0));
        $this->withSession([config('admin.session_key') => true]);
    }

    public function test_account_counts_are_global_and_deleted_accounts_are_separate(): void
    {
        User::factory()->count(55)->create(['created_at' => now()->subMonths(2), 'last_login_at' => null]);
        User::factory()->create(['is_admin' => true, 'last_login_at' => now()]);
        User::factory()->create(['suspended_at' => now(), 'last_login_at' => now()]);
        User::factory()->create(['is_admin' => true, 'last_login_at' => now()])->delete();

        $response = $this->get('/admin')->assertOk();
        $overview = $response->viewData('overview');
        $this->assertSame(57, $overview['users']);
        $this->assertSame(58, $overview['allAccounts']);
        $this->assertSame(56, $overview['enabledUsers']);
        $this->assertSame(1, $overview['suspendedUsers']);
        $this->assertSame(1, $overview['deletedUsers']);
        $this->assertSame(1, $overview['administrators']);
        $this->assertSame(1, $overview['activeUsers']);
        $this->assertSame(2, $overview['newUsers']);
        $this->assertSame(25, $response->viewData('users')->count());
        $this->assertSame(57, $response->viewData('users')->total());

        $next = $this->get('/admin?users_page=3')->assertOk();
        $this->assertSame(7, $next->viewData('users')->count());
        $this->assertSame($overview, $next->viewData('overview'));
        $this->assertStringEndsWith('#users', $next->viewData('users')->previousPageUrl());
    }

    public function test_search_and_status_filters_find_accounts_beyond_the_first_page(): void
    {
        $older = User::factory()->create(['name' => 'Older Customer', 'created_at' => now()->subYear()]);
        User::factory()->count(55)->create();
        $suspended = User::factory()->create(['name' => 'Suspended Customer', 'suspended_at' => now()]);
        $deleted = User::factory()->create(['name' => 'Deleted Customer']);
        $deleted->delete();

        $search = $this->get('/admin?user_search=Older&range=30')->assertOk();
        $this->assertSame([$older->id], $search->viewData('users')->pluck('id')->all());
        $this->assertSame('users', $search->viewData('initialView'));
        $this->assertSame(57, $search->viewData('overview')['users']);
        $this->assertSame(30, $search->viewData('analyticsDays'));
        $this->assertCount(50, $search->viewData('notificationUsers'));
        $this->assertFalse($search->viewData('notificationUsers')->contains('id', $deleted->id));

        $suspendedResults = $this->get('/admin?user_status=suspended')->assertOk();
        $this->assertSame([$suspended->id], $suspendedResults->viewData('users')->pluck('id')->all());
        $deletedResults = $this->get('/admin?user_status=deleted')->assertOk();
        $this->assertSame([$deleted->id], $deletedResults->viewData('users')->pluck('id')->all());
        $this->get('/admin?user_search=NotAnAccount')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 0);
        $this->get('/admin?user_status=enabled')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 56);
        $this->get('/admin?user_status=all')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 58);
    }

    public function test_daily_chart_fills_zero_days_and_honors_date_boundaries(): void
    {
        $owner = User::factory()->create(['created_at' => now()->subDays(40)]);
        $vehicle = Vehicle::factory()->for($owner)->create();
        User::factory()->create(['created_at' => now()->subDays(6)->startOfDay()]);
        User::factory()->create(['created_at' => now()->subDays(7)->endOfDay()]);
        User::factory()->create(['created_at' => now()])->delete();
        User::factory()->create(['created_at' => now()->addDay()]);
        foreach ([
            ['completed', now()->subDays(6)->startOfDay()],
            ['failed', now()],
            ['queued', now()],
            ['draft', now()->subDays(7)->endOfDay()],
            ['cancelled', now()->addDay()],
        ] as [$status, $date]) {
            DiagnosticSession::query()->create(['user_id' => $owner->id, 'vehicle_id' => $vehicle->id, 'status' => $status, 'created_at' => $date]);
        }
        $response = $this->get('/admin')->assertOk();
        $activity = $response->viewData('activity');
        $this->assertCount(7, $activity);
        $this->assertSame('2026-10-04', $activity->first()['date']);
        $this->assertSame(1, $activity->first()['users']);
        $this->assertSame(1, $activity->first()['diagnostics']);
        $this->assertSame(0, $activity[1]['diagnostics']);
        $this->assertSame(2, $activity->last()['diagnostics']);
        $this->assertSame(1, $activity->sum('users'));
        $this->assertSame(3, $response->viewData('diagnosticOutcomes')->sum('count'));
        $this->assertSame(2, $response->viewData('overview')['diagnosticsToday']);

        $month = $this->get('/admin?range=30')->assertOk();
        $this->assertCount(30, $month->viewData('activity'));
        $this->assertSame(2, $month->viewData('activity')->sum('users'));
        $this->assertSame(4, $month->viewData('activity')->sum('diagnostics'));
    }

    public function test_ai_success_rate_excludes_unfinished_runs_and_has_no_invented_default(): void
    {
        $this->get('/admin')->assertOk()->assertViewHas('overview', fn ($overview) => $overview['aiSuccessRate'] === null)
            ->assertSee('No finished runs yet');
        $session = DiagnosticSession::factory()->create();
        foreach (['completed', 'completed', 'failed', 'queued', 'running'] as $status) {
            AiRun::query()->create([
                'diagnostic_session_id' => $session->id, 'task_type' => 'diagnostic', 'endpoint' => '/responses',
                'model' => 'test', 'prompt_version' => 'test', 'input_hash' => hash('sha256', $status), 'status' => $status,
            ]);
        }
        $response = $this->get('/admin')->assertOk();
        $this->assertSame(66.7, $response->viewData('overview')['aiSuccessRate']);
        $this->assertSame(3, $response->viewData('overview')['terminalAiRuns']);
    }

    public function test_empty_database_renders_zero_activity(): void
    {
        $response = $this->get('/admin')->assertOk()->assertSee('No activity in this period.')->assertSee('No users found.');
        $this->assertSame(0, $response->viewData('activity')->sum('diagnostics'));
        $this->assertSame(0, $response->viewData('overview')['users']);
    }
}
