<?php

namespace Tests\Feature;

use App\Contracts\ReportAssistantProvider;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticReportTranslation;
use App\Models\DiagnosticSession;
use App\Models\MaintenanceReminder;
use App\Models\Mechanic;
use App\Models\ReportAction;
use App\Models\ReportActionTranslation;
use App\Models\ServiceQuote;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAiProviders;

class ProductGapsApiTest extends ApiTestCase
{
    public function test_report_summaries_are_paginated_and_unread_count_is_global(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $this->createReport($user, $vehicle, 'First report');
        $this->createReport($user, $vehicle, 'Second report');

        foreach (range(1, 3) as $index) {
            UserNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'test',
                'title_en' => "Notification $index",
                'title_ar' => "إشعار $index",
                'body_en' => 'Body',
                'body_ar' => 'المحتوى',
            ]);
        }

        $this->getJson('/api/v1/reports?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['meta' => ['nextCursor']]);

        $this->getJson('/api/v1/notifications?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unreadCount', 3);
    }

    public function test_availability_and_appointments_enforce_mechanic_local_working_hours(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $mechanic = Mechanic::factory()->create([
            'timezone' => 'Africa/Cairo',
            'working_hours_json' => [
                'mon' => ['09:00', '17:00'],
            ],
        ]);
        $day = CarbonImmutable::now('Africa/Cairo')->next('Monday')->startOfDay();

        $this->getJson('/api/v1/mechanics/'.$mechanic->id.'/availability?'.http_build_query([
            'date' => $day->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Africa/Cairo')
            ->assertJsonCount(8, 'data.availableSlots')
            ->assertJsonPath('data.availableSlots.0.start', $day->setTime(9, 0)->utc()->toIso8601ZuluString());

        $this->postJson('/api/v1/appointments', [
            'mechanicId' => $mechanic->id,
            'vehicleId' => $vehicle->id,
            'requestedStart' => $day->setTime(8, 0)->utc()->toIso8601String(),
            'requestedEnd' => $day->setTime(9, 0)->utc()->toIso8601String(),
        ])->assertStatus(409)->assertJsonPath('error.code', 'APPOINTMENT_OUTSIDE_WORKING_HOURS');

        $this->postJson('/api/v1/appointments', [
            'mechanicId' => $mechanic->id,
            'vehicleId' => $vehicle->id,
            'requestedStart' => $day->setTime(9, 0)->utc()->toIso8601String(),
            'requestedEnd' => $day->setTime(10, 0)->utc()->toIso8601String(),
        ])->assertCreated();
    }

    public function test_report_follow_ups_and_action_to_maintenance_conversion_are_persisted(): void
    {
        $fake = new FakeAiProviders;
        $this->app->instance(ReportAssistantProvider::class, $fake);
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $report = $this->createReport($user, $vehicle, 'Misfire report');
        $action = ReportAction::query()->create([
            'diagnostic_report_id' => $report->id,
            'action_type' => 'recommended_action',
            'canonical_code' => 'professional_inspection',
            'priority' => 1,
            'professional_required' => true,
        ]);
        ReportActionTranslation::query()->create([
            'report_action_id' => $action->id,
            'locale' => 'en',
            'text' => 'Arrange a professional inspection.',
        ]);
        ReportActionTranslation::query()->create([
            'report_action_id' => $action->id,
            'locale' => 'ar',
            'text' => 'رتب فحصاً متخصصاً.',
        ]);

        $this->postJson("/api/v1/reports/$report->id/follow-ups", [
            'question' => 'Does the new vibration change the recommendation?',
        ])->assertCreated()
            ->assertJsonPath('data.confidence', 0.78)
            ->assertJsonPath('data.professionalInspectionRequired', true);
        $this->getJson("/api/v1/reports/$report->id/follow-ups")
            ->assertOk()->assertJsonCount(1, 'data');

        $first = $this->postJson("/api/v1/reports/$report->id/maintenance-reminders", [
            'actionIds' => [$action->id],
        ])->assertCreated()
            ->assertJsonPath('data.0.sourceReportId', (string) $report->id)
            ->assertJsonPath('data.0.sourceReportActionId', (string) $action->id);
        $second = $this->postJson("/api/v1/reports/$report->id/maintenance-reminders", [
            'actionIds' => [$action->id],
        ])->assertCreated();

        $this->assertSame($first->json('data.0.id'), $second->json('data.0.id'));
        $this->assertSame(1, MaintenanceReminder::query()->count());
        $this->assertSame('follow-up', $fake->calls[0][0]);
    }

    public function test_workshops_can_quote_chat_and_advance_repair_status(): void
    {
        $consumer = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($consumer)->create();
        $owner = User::factory()->create();
        $mechanic = Mechanic::factory()->create(['owner_user_id' => $owner->id]);
        $secondOwner = User::factory()->create();
        $secondMechanic = Mechanic::factory()->create(['owner_user_id' => $secondOwner->id]);

        $created = $this->withHeader('Idempotency-Key', 'quote-request-1')
            ->postJson('/api/v1/service-requests', [
                'vehicleId' => $vehicle->id,
                'mechanicIds' => [$mechanic->id, $secondMechanic->id],
                'description' => 'Inspect the front brakes.',
            ])->assertCreated();
        $requestId = $created->json('data.id');

        Sanctum::actingAs($owner);
        $quoted = $this->postJson("/api/v1/mechanic/service-requests/$requestId/quote", [
            'currency' => 'EGP',
            'laborAmount' => '500.00',
            'partsAmount' => '1200.00',
            'feesAmount' => '100.00',
            'estimatedDurationMinutes' => 120,
            'lineItems' => [
                ['label' => 'Brake pads', 'category' => 'part', 'amount' => '1200.00'],
                ['label' => 'Installation', 'category' => 'labor', 'amount' => '500.00'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'quotes_ready')
            ->assertJsonPath('data.quotes.0.totalAmount', 1800);
        $quoteId = $quoted->json('data.quotes.0.id');

        Sanctum::actingAs($secondOwner);
        $secondQuoted = $this->postJson("/api/v1/mechanic/service-requests/$requestId/quote", [
            'currency' => 'EGP',
            'laborAmount' => '450.00',
            'partsAmount' => '1300.00',
            'feesAmount' => '0.00',
            'estimatedDurationMinutes' => 90,
        ])->assertCreated()
            ->assertJsonCount(2, 'data.quotes');
        $secondQuoteId = collect($secondQuoted->json('data.quotes'))
            ->firstWhere('mechanicId', $secondMechanic->id)['id'];

        Sanctum::actingAs($consumer);
        $this->postJson("/api/v1/service-requests/$requestId/messages", [
            'body' => 'Does the quote include installation?',
        ])->assertCreated()->assertJsonPath('data.messages.0.senderRole', 'customer');
        $this->postJson("/api/v1/service-requests/$requestId/quotes/$quoteId/accept")
            ->assertOk()->assertJsonPath('data.status', 'accepted');

        Sanctum::actingAs($secondOwner);
        $this->patchJson("/api/v1/mechanic/service-requests/$requestId/status", [
            'status' => 'in_service',
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/mechanic/service-requests/$requestId/status", [
            'status' => 'in_service',
        ])->assertOk()->assertJsonPath('data.status', 'in_service');
        $this->patchJson("/api/v1/mechanic/service-requests/$requestId/status", [
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame('accepted', ServiceQuote::query()->findOrFail($quoteId)->status);
        $this->assertSame('declined', ServiceQuote::query()->findOrFail($secondQuoteId)->status);
    }

    private function createReport(User $user, Vehicle $vehicle, string $title): DiagnosticReport
    {
        $session = DiagnosticSession::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'completed',
        ]);
        $report = DiagnosticReport::query()->create([
            'diagnostic_session_id' => $session->id,
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'overall_confidence' => 0.82,
            'severity' => 'high',
            'driving_recommendation' => 'stopSoon',
            'evidence_quality' => 'moderate',
            'professional_inspection_required' => true,
            'prompt_version' => 'diagnostic-v1',
            'schema_version' => 'report-v1',
            'generated_at' => now(),
            'disclaimer_version' => 'v1',
        ]);
        foreach (['en', 'ar'] as $locale) {
            DiagnosticReportTranslation::query()->create([
                'diagnostic_report_id' => $report->id,
                'locale' => $locale,
                'title' => $title,
                'summary' => 'Summary',
                'driving_advice' => 'Arrange an inspection.',
                'disclaimer' => 'Guidance only.',
            ]);
        }

        return $report;
    }
}
