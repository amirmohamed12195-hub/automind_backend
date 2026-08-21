<?php

namespace Database\Seeders;

use App\Models\MaintenanceServiceDefinition;
use App\Models\MechanicSpecialty;
use App\Models\SymptomDefinition;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedSymptoms();
            $this->seedVehicleCatalog();
            $this->seedMaintenanceServices();
            $this->seedMechanicSpecialties();
        });

        $this->call(BillingCatalogSeeder::class);
    }

    private function seedSymptoms(): void
    {
        foreach ([
            ['engine', 'Engine', 'المحرك'],
            ['performance', 'Performance', 'الأداء'],
            ['brakes', 'Brakes', 'الفرامل'],
            ['transmission', 'Transmission', 'ناقل الحركة'],
            ['suspension', 'Suspension and steering', 'نظام التعليق والتوجيه'],
            ['electrical', 'Electrical', 'النظام الكهربائي'],
            ['acHeating', 'A/C and heating', 'التكييف والتدفئة'],
            ['tires', 'Tires and wheels', 'الإطارات والعجلات'],
            ['other', 'Other', 'أخرى'],
        ] as $index => [$code, $english, $arabic]) {
            SymptomDefinition::query()->updateOrCreate(
                ['code' => $code],
                [
                    'label_en' => $english,
                    'label_ar' => $arabic,
                    'active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    private function seedVehicleCatalog(): void
    {
        /** @var array<string, string> $makeLogos */
        $makeLogos = json_decode(
            file_get_contents(database_path('data/vehicle_makes.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($makeLogos as $english => $filename) {
            $code = preg_replace('/-logo\.(?:svg|png)$/', '', $filename);
            VehicleMake::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_en' => $english,
                    'name_ar' => $english,
                    'logo_path' => 'images/vehicle-makes/'.$filename,
                    'active' => true,
                ],
            );
        }

        /** @var array<int, array{code: string, name_en: string, name_ar: string, models: array<int, array{0: string, 1: string, 2: string}>}> $catalog */
        $catalog = require database_path('data/vehicle_catalog.php');

        foreach ($catalog as $makeData) {
            $make = VehicleMake::query()->updateOrCreate(
                ['code' => $makeData['code']],
                [
                    'name_en' => $makeData['name_en'],
                    'name_ar' => $makeData['name_ar'],
                    'active' => true,
                ],
            );

            foreach ($makeData['models'] as [$code, $english, $arabic]) {
                VehicleModel::query()->updateOrCreate(
                    ['make_id' => $make->id, 'code' => $code],
                    [
                        'name_en' => $english,
                        'name_ar' => $arabic,
                        'active' => true,
                    ],
                );
            }
        }
    }

    private function seedMaintenanceServices(): void
    {
        foreach ([
            ['oil_change', 'Engine oil and filter', 'زيت وفلتر المحرك', 6, 10000],
            ['air_filter', 'Engine air filter', 'فلتر هواء المحرك', 12, 20000],
            ['cabin_filter', 'Cabin air filter', 'فلتر هواء المقصورة', 12, 15000],
            ['brake_inspection', 'Brake inspection', 'فحص الفرامل', 12, 20000],
            ['brake_fluid', 'Brake fluid', 'سائل الفرامل', 24, 40000],
            ['coolant', 'Engine coolant', 'سائل تبريد المحرك', 36, 60000],
            ['transmission_fluid', 'Transmission fluid', 'زيت ناقل الحركة', 48, 60000],
            ['spark_plugs', 'Spark plugs', 'شمعات الإشعال', 48, 60000],
            ['tire_rotation', 'Tire rotation', 'تدوير الإطارات', 6, 10000],
            ['tire_inspection', 'Tire inspection', 'فحص الإطارات', 6, 10000],
            ['wheel_alignment', 'Wheel alignment', 'ضبط زوايا العجلات', 12, 20000],
            ['battery_test', 'Battery and charging test', 'فحص البطارية والشحن', 12, 20000],
            ['ac_inspection', 'A/C inspection', 'فحص التكييف', 12, 20000],
            ['timing_belt', 'Timing belt inspection', 'فحص سير التوقيت', 60, 100000],
            ['general_inspection', 'General safety inspection', 'فحص السلامة العام', 12, 20000],
        ] as [$code, $english, $arabic, $months, $kilometers]) {
            MaintenanceServiceDefinition::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_en' => $english,
                    'name_ar' => $arabic,
                    'description_en' => $english,
                    'description_ar' => $arabic,
                    'default_month_interval' => $months,
                    'default_km_interval' => $kilometers,
                    'active' => true,
                ],
            );
        }
    }

    private function seedMechanicSpecialties(): void
    {
        foreach ([
            ['general', 'General repair', 'صيانة عامة'],
            ['engine', 'Engine', 'المحرك'],
            ['transmission', 'Transmission', 'ناقل الحركة'],
            ['brakes', 'Brakes', 'الفرامل'],
            ['suspension', 'Suspension and steering', 'التعليق والتوجيه'],
            ['electrical', 'Electrical and diagnostics', 'الكهرباء والتشخيص'],
            ['air-conditioning', 'Air conditioning', 'التكييف'],
            ['tires', 'Tires and alignment', 'الإطارات وضبط الزوايا'],
            ['body', 'Body and paint', 'السمكرة والدهان'],
            ['hybrid-ev', 'Hybrid and electric vehicles', 'السيارات الهجينة والكهربائية'],
        ] as [$code, $english, $arabic]) {
            MechanicSpecialty::query()->updateOrCreate(
                ['code' => $code],
                ['name_en' => $english, 'name_ar' => $arabic],
            );
        }
    }
}
