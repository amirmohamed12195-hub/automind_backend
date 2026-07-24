<?php

namespace Tests\Feature;

use App\Models\MaintenanceServiceDefinition;
use App\Models\MechanicSpecialty;
use App\Models\SymptomDefinition;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Database\Seeders\ReferenceDataSeeder;

class ReferenceDataSeederTest extends ApiTestCase
{
    public function test_reference_data_is_complete_and_idempotent(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $firstCounts = [
            VehicleMake::query()->count(),
            VehicleModel::query()->count(),
            SymptomDefinition::query()->count(),
            MaintenanceServiceDefinition::query()->count(),
            MechanicSpecialty::query()->count(),
        ];

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame($firstCounts, [
            VehicleMake::query()->count(),
            VehicleModel::query()->count(),
            SymptomDefinition::query()->count(),
            MaintenanceServiceDefinition::query()->count(),
            MechanicSpecialty::query()->count(),
        ]);
        $this->assertGreaterThanOrEqual(40, $firstCounts[0]);
        $this->assertGreaterThanOrEqual(200, $firstCounts[1]);
        $this->assertSame(9, $firstCounts[2]);
        $this->assertSame(15, $firstCounts[3]);
        $this->assertSame(10, $firstCounts[4]);

        $this->getJson('/api/v1/vehicle-catalog/makes')
            ->assertOk()
            ->assertJsonFragment(['code' => 'toyota', 'name' => 'Toyota']);
        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models')
            ->assertOk()
            ->assertJsonFragment(['code' => 'corolla', 'name' => 'Corolla']);
    }
}
