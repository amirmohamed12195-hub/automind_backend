<?php

namespace Database\Seeders;

use App\Models\MaintenanceServiceDefinition;
use App\Models\Mechanic;
use App\Models\MechanicSpecialty;
use App\Models\SymptomDefinition;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! config('automind.allow_demo_seeding')) {
            throw new RuntimeException('Demo seeding is disabled in production. Set ALLOW_DEMO_SEEDING=true only for an isolated non-production environment.');
        }
        $user = User::factory()->create([
            'name' => 'AutoMind Demo',
            'email' => 'demo@automind.test',
        ]);

        $admin = User::factory()->create([
            'name' => 'AutoMind Admin',
            'email' => 'admin@automind.test',
            'is_admin' => true,
        ]);

        $symptoms = [
            ['engine', 'Engine', 'المحرك'],
            ['performance', 'Performance', 'الأداء'],
            ['brakes', 'Brakes', 'الفرامل'],
            ['transmission', 'Transmission', 'ناقل الحركة'],
            ['suspension', 'Suspension', 'نظام التعليق'],
            ['electrical', 'Electrical', 'النظام الكهربائي'],
            ['acHeating', 'A/C and heating', 'التكييف والتدفئة'],
            ['tires', 'Tires', 'الإطارات'],
            ['other', 'Other', 'أخرى'],
        ];
        foreach ($symptoms as $index => [$code, $en, $ar]) {
            SymptomDefinition::query()->create(['code' => $code, 'label_en' => $en, 'label_ar' => $ar, 'active' => true, 'sort_order' => $index + 1]);
        }

        $toyota = VehicleMake::query()->create(['code' => 'toyota', 'name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'active' => true]);
        $corolla = VehicleModel::query()->create(['make_id' => $toyota->id, 'code' => 'corolla', 'name_en' => 'Corolla', 'name_ar' => 'كورولا', 'start_year' => 1966, 'active' => true]);
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id, 'catalog_make_id' => $toyota->id, 'catalog_model_id' => $corolla->id,
            'brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'engine' => '1.6L',
            'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'mileage_km' => 145000, 'health_score' => 78,
        ]);
        DB::table('user_selected_vehicles')->insert(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'created_at' => now(), 'updated_at' => now()]);

        foreach ([
            ['oil_change', 'Engine oil change', 'تغيير زيت المحرك', 6, 10000],
            ['brake_inspection', 'Brake inspection', 'فحص الفرامل', 12, 20000],
            ['tire_rotation', 'Tire rotation', 'تدوير الإطارات', 6, 10000],
        ] as [$code, $en, $ar, $months, $km]) {
            MaintenanceServiceDefinition::query()->create([
                'code' => $code, 'name_en' => $en, 'name_ar' => $ar,
                'description_en' => $en, 'description_ar' => $ar,
                'default_month_interval' => $months, 'default_km_interval' => $km, 'active' => true,
            ]);
        }

        $specialty = MechanicSpecialty::query()->create(['code' => 'general', 'name_en' => 'General repair', 'name_ar' => 'صيانة عامة']);
        $mechanic = Mechanic::query()->create([
            'owner_user_id' => $admin->id, 'name_en' => 'Cairo Auto Care', 'name_ar' => 'مركز القاهرة للسيارات',
            'description_en' => 'Verified general automotive workshop.', 'description_ar' => 'ورشة موثقة للصيانة العامة.',
            'phone' => '+201000000000', 'address' => 'Nasr City', 'city' => 'Cairo', 'country_code' => 'EG',
            'latitude' => 30.0561, 'longitude' => 31.3300, 'rating_average' => 4.7, 'rating_count' => 124,
            'verified' => true, 'active' => true,
            'working_hours_json' => ['sun' => ['09:00', '18:00'], 'mon' => ['09:00', '18:00']],
        ]);
        $mechanic->specialties()->attach($specialty->id);
    }
}
