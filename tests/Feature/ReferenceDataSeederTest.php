<?php

namespace Tests\Feature;

use App\Models\MaintenanceServiceDefinition;
use App\Models\MechanicSpecialty;
use App\Models\SymptomDefinition;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleModelGeneration;
use Database\Seeders\ReferenceDataSeeder;

class ReferenceDataSeederTest extends ApiTestCase
{
    public function test_reference_data_is_complete_and_idempotent(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $firstCounts = [
            VehicleMake::query()->count(),
            VehicleModel::query()->count(),
            VehicleModelGeneration::query()->count(),
            SymptomDefinition::query()->count(),
            MaintenanceServiceDefinition::query()->count(),
            MechanicSpecialty::query()->count(),
        ];

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame($firstCounts, [
            VehicleMake::query()->count(),
            VehicleModel::query()->count(),
            VehicleModelGeneration::query()->count(),
            SymptomDefinition::query()->count(),
            MaintenanceServiceDefinition::query()->count(),
            MechanicSpecialty::query()->count(),
        ]);
        $this->assertSame(184, $firstCounts[0]);
        $this->assertSame(5904, $firstCounts[1]);
        $this->assertSame(10181, $firstCounts[2]);
        $this->assertSame(0, VehicleMake::query()->whereDoesntHave('models')->count());
        $this->assertSame(0, VehicleMake::query()->whereNull('logo_path')->count());
        $this->assertSame([], VehicleMake::query()->pluck('logo_path')
            ->filter(fn (string $path): bool => ! is_file(public_path($path)))
            ->values()
            ->all());
        $this->assertSame(0, VehicleModel::query()->whereDoesntHave('generations')->count());
        $this->assertSame(6595, VehicleModelGeneration::query()->where('data_source', 'vehicle-makes-models')->count());
        $this->assertSame(9, $firstCounts[3]);
        $this->assertSame(15, $firstCounts[4]);
        $this->assertSame(10, $firstCounts[5]);

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
            ->assertJsonFragment(['code' => 'corolla', 'name' => 'Corolla', 'imageType' => 'brand_logo'])
            ->assertJsonPath('meta.customModelAllowed', true);
        $this->getJson('/api/v1/vehicle-catalog/makes/toyota/models/corolla/generations?year=2018')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'corolla-sedan-2016',
                'name' => 'Corolla Sedan (2016)',
                'imageType' => 'brand_logo',
            ]);
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
