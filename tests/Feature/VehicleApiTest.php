<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

class VehicleApiTest extends ApiTestCase
{
    public function test_vehicle_crud_selected_and_health_payload_matches_flutter_contract(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = $this->actingAsUser();
        $payload = ['brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'engine' => '1.6L', 'fuelType' => 'Petrol', 'transmission' => 'Automatic', 'mileage' => 145000];
        $created = $this->postJson('/api/v1/vehicles', $payload)->assertCreated()->assertJsonStructure(['data' => ['id', 'userId', 'brand', 'model', 'year', 'engine', 'fuelType', 'transmission', 'mileage', 'vin', 'imagePath', 'brandLogoUrl', 'catalogMakeId', 'catalogModelId', 'healthScore', 'isSelected', 'createdAt', 'updatedAt']]);
        $id = $created->json('data.id');
        $this->assertSame($user->id, $created->json('data.userId'));
        $this->assertNotNull($created->json('data.catalogMakeId'));
        $this->assertSame('http://localhost/images/vehicle-makes/toyota-logo.svg', $created->json('data.brandLogoUrl'));
        $this->putJson("/api/v1/vehicles/$id/selected")->assertOk()->assertJsonPath('data.isSelected', true);
        $this->patchJson("/api/v1/vehicles/$id", ['mileage' => 150000])->assertOk()->assertJsonPath('data.mileage', 150000);
        $this->getJson("/api/v1/vehicles/$id/health")->assertOk()->assertJsonPath('data.healthScore', 100);
        $this->deleteJson("/api/v1/vehicles/$id")->assertNoContent();
    }

    public function test_cross_user_vehicle_access_is_forbidden_and_vin_is_unique_per_user(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create(['vin' => 'JTDBR32E720123456']);
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/vehicles/$vehicle->id")->assertForbidden();
        $payload = ['brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'engine' => '1.6L', 'fuelType' => 'Petrol', 'transmission' => 'Automatic', 'mileage' => 100, 'vin' => 'JTDBR32E720123456'];
        $this->postJson('/api/v1/vehicles', $payload)->assertCreated();
        $this->postJson('/api/v1/vehicles', $payload)->assertUnprocessable()->assertJsonPath('error.code', 'VIN_ALREADY_EXISTS');
    }

    public function test_catalog_model_must_belong_to_selected_make(): void
    {
        $this->actingAsUser();
        $make = VehicleMake::query()->create(['code' => 'toyota', 'name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'active' => true]);
        $otherMake = VehicleMake::query()->create(['code' => 'honda', 'name_en' => 'Honda', 'name_ar' => 'هوندا', 'active' => true]);
        $model = VehicleModel::query()->create(['make_id' => $otherMake->id, 'code' => 'civic', 'name_en' => 'Civic', 'name_ar' => 'سيفيك', 'active' => true]);

        $this->postJson('/api/v1/vehicles', [
            'brand' => 'Toyota', 'model' => 'Civic', 'year' => 2018, 'engine' => '1.6L', 'fuelType' => 'Petrol',
            'transmission' => 'Automatic', 'mileage' => 100, 'catalogMakeId' => $make->id, 'catalogModelId' => $model->id,
        ])->assertUnprocessable()->assertJsonPath('error.details.catalogModelId.0', __('api.catalog_model_make_mismatch'));
    }

    public function test_custom_model_is_accepted_for_a_catalog_make(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->actingAsUser();
        $make = VehicleMake::query()->where('code', 'aito')->sole();

        $this->postJson('/api/v1/vehicles', [
            'brand' => 'AITO', 'model' => 'Regional Special Edition', 'year' => 2026, 'engine' => 'Electric',
            'fuelType' => 'Electric', 'transmission' => 'Automatic', 'mileage' => 0, 'catalogMakeId' => $make->id,
            'catalogModelId' => null,
        ])->assertCreated()
            ->assertJsonPath('data.model', 'Regional Special Edition')
            ->assertJsonPath('data.catalogMakeId', (string) $make->id)
            ->assertJsonPath('data.catalogModelId', null)
            ->assertJsonPath('data.brandLogoUrl', 'http://localhost/images/vehicle-makes/aito-logo.svg');
    }
}
