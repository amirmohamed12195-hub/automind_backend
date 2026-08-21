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
        $this->assertSame(184, $firstCounts[0]);
        $this->assertSame(5904, $firstCounts[1]);
        $this->assertSame(0, VehicleMake::query()->whereDoesntHave('models')->count());
        $this->assertSame(9, $firstCounts[2]);
        $this->assertSame(15, $firstCounts[3]);
        $this->assertSame(10, $firstCounts[4]);

        $this->getJson('/api/v1/vehicle-catalog/makes')
            ->assertOk()
            ->assertJsonCount(184, 'data')
            ->assertJsonFragment([
                'code' => 'toyota',
                'name' => 'Toyota',
                'logoUrl' => 'http://localhost/images/vehicle-makes/toyota-logo.svg',
                'hasModels' => true,
                'allowsCustomModel' => true,
            ])
            ->assertJsonFragment([
                'code' => 'rivian',
                'name' => 'Rivian',
                'logoUrl' => 'http://localhost/images/vehicle-makes/rivian-logo.svg',
                'hasModels' => true,
                'modelCount' => 5,
                'allowsCustomModel' => true,
            ]);
        $this->assertFileExists(public_path('images/vehicle-makes/toyota-logo.svg'));
        $this->assertFileExists(public_path('images/vehicle-makes/rivian-logo.svg'));
        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models')
            ->assertOk()
            ->assertJsonFragment(['code' => 'corolla', 'name' => 'Corolla'])
            ->assertJsonPath('meta.customModelAllowed', true);
        $this->getJson('/api/v1/vehicle-catalog/makes/lexus/models')
            ->assertOk()
            ->assertJsonFragment(['code' => 'es', 'name' => 'ES']);
        $this->getJson('/api/v1/vehicle-catalog/makes/aito/models')
            ->assertOk()
            ->assertJsonFragment(['code' => 'm9', 'name' => 'M9']);
        $this->getJson('/api/v1/vehicle-catalog/makes/w-motors/models')
            ->assertOk()
            ->assertJsonFragment(['code' => 'lykan-hypersport', 'name' => 'Lykan HyperSport']);
    }
}
