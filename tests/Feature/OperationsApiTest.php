<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CurrencyRate;
use App\Models\LaborRateSource;
use App\Models\MaintenanceServiceDefinition;
use App\Models\Mechanic;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use Laravel\Sanctum\Sanctum;

class OperationsApiTest extends ApiTestCase
{
    public function test_active_maintenance_service_definitions_are_public_and_localized(): void
    {
        MaintenanceServiceDefinition::query()->create([
            'code' => 'oil_change',
            'name_en' => 'Oil change',
            'name_ar' => 'تغيير الزيت',
            'description_en' => 'Replace engine oil.',
            'description_ar' => 'استبدال زيت المحرك.',
            'default_month_interval' => 6,
            'default_km_interval' => 10000,
            'active' => true,
        ]);
        MaintenanceServiceDefinition::query()->create([
            'code' => 'legacy_service',
            'name_en' => 'Legacy service',
            'name_ar' => 'خدمة قديمة',
            'active' => false,
        ]);

        $this->getJson('/api/v1/maintenance-service-definitions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Oil change')
            ->assertJsonPath('data.0.defaultKmInterval', 10000);

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/maintenance-service-definitions')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'تغيير الزيت')
            ->assertJsonPath('data.0.description', 'استبدال زيت المحرك.');
    }

    public function test_maintenance_history_and_reminder_completion_preserve_odometer(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create(['mileage_km' => 100000]);
        $service = MaintenanceServiceDefinition::query()->create(['code' => 'oil_change', 'name_en' => 'Oil change', 'name_ar' => 'تغيير الزيت', 'active' => true]);
        $record = $this->postJson("/api/v1/vehicles/$vehicle->id/maintenance", ['serviceDefinitionId' => $service->id, 'serviceDate' => today()->subDay()->toDateString(), 'odometerKm' => 90000, 'amount' => '500.00', 'currency' => 'EGP'])->assertCreated();
        $this->assertSame(100000, $vehicle->fresh()->mileage_km);
        $this->getJson("/api/v1/vehicles/$vehicle->id/maintenance/{$record->json('data.id')}")->assertOk()->assertJsonPath('data.odometerKm', 90000);
        $reminder = $this->postJson("/api/v1/vehicles/$vehicle->id/maintenance-reminders", ['serviceDefinitionId' => $service->id, 'dueKm' => 95000, 'notificationPreferences' => ['daysBefore' => 7]])
            ->assertCreated()->assertJsonPath('data.notificationPreferences.daysBefore', 7);
        $this->getJson("/api/v1/vehicles/$vehicle->id/maintenance-reminders")->assertOk()->assertJsonPath('data.0.isDue', true);
        $this->patchJson("/api/v1/vehicles/$vehicle->id/maintenance-reminders/{$reminder->json('data.id')}", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->postJson("/api/v1/vehicles/$vehicle->id/maintenance-reminders/{$reminder->json('data.id')}/complete", ['serviceDate' => today()->toDateString(), 'odometerKm' => 100000])->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_mobile_cursor_endpoints_reject_invalid_page_sizes(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/diagnoses?limit=0')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson('/api/v1/appointments?limit=51')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson('/api/v1/notifications?limit=-1')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_mechanics_are_localized_and_appointment_conflicts_are_atomic(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $mechanic = Mechanic::factory()->create(['name_en' => 'Cairo Auto', 'name_ar' => 'مركز القاهرة']);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/mechanics?latitude=30.04&longitude=31.23&radiusKm=20')->assertOk()->assertJsonPath('data.0.name', 'مركز القاهرة');
        $start = now()->addDay()->startOfHour();
        $end = $start->copy()->addHour();
        $payload = ['mechanicId' => $mechanic->id, 'vehicleId' => $vehicle->id, 'requestedStart' => $start->toIso8601String(), 'requestedEnd' => $end->toIso8601String()];
        $appointment = $this->withHeader('Idempotency-Key', 'booking-1')->postJson('/api/v1/appointments', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', 'booking-1')->postJson('/api/v1/appointments', $payload)->assertOk()->assertJsonPath('data.id', $appointment->json('data.id'));
        $this->withHeader('Idempotency-Key', 'booking-2')->postJson('/api/v1/appointments', $payload)->assertStatus(409)->assertJsonPath('error.code', 'APPOINTMENT_CONFLICT');
        $this->postJson("/api/v1/appointments/{$appointment->json('data.id')}/cancel", ['reason' => 'Plans changed'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_appointment_idor_notifications_and_admin_protection(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $mechanic = Mechanic::factory()->create();
        $appointment = Appointment::query()->create(['user_id' => $owner->id, 'mechanic_id' => $mechanic->id, 'vehicle_id' => $vehicle->id, 'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHour(), 'status' => 'requested']);
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/appointments/$appointment->id")->assertForbidden();
        $this->getJson('/api/v1/admin/mechanics')->assertForbidden();
        $notification = UserNotification::query()->create(['user_id' => $other->id, 'type' => 'test', 'title_en' => 'Test', 'title_ar' => 'اختبار', 'body_en' => 'Body', 'body_ar' => 'المحتوى']);
        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->assertNotNull($notification->fresh()->read_at);
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/mechanics')->assertOk();
    }

    public function test_admin_can_append_validated_labor_and_currency_reference_data(): void
    {
        $this->actingAsUser(['is_admin' => true]);
        $labor = [
            'countryCode' => 'eg', 'city' => 'Cairo', 'serviceCategory' => 'default',
            'hourlyLow' => '100.00', 'hourlyTypical' => '150.00', 'hourlyHigh' => '200.00',
            'hoursLow' => '1.00', 'hoursTypical' => '1.50', 'hoursHigh' => '2.00',
            'currency' => 'egp', 'observedAt' => now()->subMinute()->toIso8601String(), 'expiresAt' => now()->addMonth()->toIso8601String(),
        ];
        $this->postJson('/api/v1/admin/labor-rate-sources', [...$labor, 'hourlyTypical' => '50.00'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->postJson('/api/v1/admin/labor-rate-sources', $labor)
            ->assertCreated()->assertJsonPath('data.countryCode', 'EG')->assertJsonPath('data.currency', 'EGP');
        $this->getJson('/api/v1/admin/labor-rate-sources?countryCode=EG&serviceCategory=default')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/admin/currency-rates', [
            'baseCurrency' => 'usd', 'quoteCurrency' => 'egp', 'rate' => '50.1250000000',
            'provider' => 'central-bank-fixture', 'effectiveAt' => now()->subMinute()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.baseCurrency', 'USD')->assertJsonPath('data.quoteCurrency', 'EGP');
        $this->getJson('/api/v1/admin/currency-rates?baseCurrency=USD&quoteCurrency=EGP')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame(1, LaborRateSource::query()->count());
        $this->assertSame(1, CurrencyRate::query()->count());
        $this->assertSame(2, AuditLog::query()->whereIn('action', ['admin.labor_rate.created', 'admin.currency_rate.created'])->count());
    }
}
