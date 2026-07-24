<?php

namespace Database\Seeders;

use App\Models\Mechanic;
use App\Models\MechanicSpecialty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment('production') && ! config('automind.allow_demo_seeding')) {
            $this->command?->info('Reference data seeded. Demo accounts and workshop were skipped in production.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@automind.test'],
            ['name' => 'AutoMind Demo'],
        );
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@automind.test'],
            ['name' => 'AutoMind Admin'],
        );
        $admin->forceFill(['is_admin' => true])->save();

        $toyota = VehicleMake::query()->where('code', 'toyota')->sole();
        $corolla = VehicleModel::query()->whereBelongsTo($toyota, 'make')->where('code', 'corolla')->sole();
        $vehicle = Vehicle::query()->firstOrCreate(
            ['user_id' => $user->id, 'vin' => null],
            [
                'catalog_make_id' => $toyota->id, 'catalog_model_id' => $corolla->id,
                'brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'engine' => '1.6L',
                'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'mileage_km' => 145000, 'health_score' => 78,
            ],
        );
        DB::table('user_selected_vehicles')->updateOrInsert(
            ['user_id' => $user->id],
            ['vehicle_id' => $vehicle->id, 'created_at' => now(), 'updated_at' => now()],
        );

        $mechanic = Mechanic::query()->updateOrCreate(['phone' => '+201000000000'], [
            'owner_user_id' => $admin->id, 'name_en' => 'Cairo Auto Care', 'name_ar' => 'مركز القاهرة للسيارات',
            'description_en' => 'Verified general automotive workshop.', 'description_ar' => 'ورشة موثقة للصيانة العامة.',
            'address' => 'Nasr City', 'city' => 'Cairo', 'country_code' => 'EG',
            'latitude' => 30.0561, 'longitude' => 31.3300, 'rating_average' => 4.7, 'rating_count' => 124,
            'verified' => true, 'active' => true,
            'working_hours_json' => ['sun' => ['09:00', '18:00'], 'mon' => ['09:00', '18:00']],
        ]);
        $mechanic->specialties()->syncWithoutDetaching(
            MechanicSpecialty::query()->where('code', 'general')->pluck('id'),
        );
    }
}
