<?php

namespace Database\Seeders;

use App\Models\BillingPlan;
use App\Models\StoreProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $plans = [
                'FREE' => [
                    'type' => 'free', 'sort_order' => 10, 'recommended' => false,
                    'default_for_new_users' => true, 'max_vehicles' => 1, 'reports_per_period' => 1,
                    'en' => ['Free', 'Get started with essential vehicle guidance.', ['One vehicle', 'One introductory diagnosis', 'Basic OBD code explanation', 'Limited diagnostic history']],
                    'ar' => ['مجاني', 'ابدأ بإرشادات السيارة الأساسية.', ['سيارة واحدة', 'تشخيص تمهيدي واحد', 'شرح أساسي لأكواد OBD', 'سجل تشخيصات محدود']],
                    'features' => ['basic_diagnosis' => true, 'advanced_obd' => false, 'full_history' => false, 'pdf_sharing' => false, 'maintenance_reminders' => false, 'priority_analysis' => false],
                ],
                'SINGLE_FULL_REPORT' => [
                    'type' => 'consumable', 'sort_order' => 20, 'recommended' => false,
                    'default_for_new_users' => false, 'max_vehicles' => null, 'reports_per_period' => null,
                    'en' => ['One full report', 'Purchase one full diagnostic report credit.', ['One full diagnostic report', 'Evidence and next steps', 'Credit does not expire']],
                    'ar' => ['تقرير كامل واحد', 'اشترِ رصيداً لتقرير تشخيص كامل واحد.', ['تقرير تشخيص كامل واحد', 'الأدلة والخطوات التالية', 'الرصيد لا تنتهي صلاحيته']],
                    'features' => ['full_diagnosis' => true, 'advanced_obd' => false, 'full_history' => false, 'pdf_sharing' => false, 'maintenance_reminders' => false, 'priority_analysis' => false],
                ],
                'PLUS_MONTHLY' => [
                    'type' => 'subscription', 'sort_order' => 30, 'recommended' => true,
                    'default_for_new_users' => false, 'max_vehicles' => 3, 'reports_per_period' => 4,
                    'en' => ['AutoMind Plus Monthly', 'Advanced diagnostics with monthly flexibility.', ['Up to three vehicles', 'Four full reports per billing period', 'Advanced OBD features', 'Full history', 'PDF sharing', 'Maintenance reminders', 'Priority analysis']],
                    'ar' => ['أوتومايند بلس شهري', 'تشخيص متقدم بمرونة شهرية.', ['حتى ثلاث سيارات', 'أربعة تقارير كاملة لكل فترة فوترة', 'ميزات OBD متقدمة', 'السجل الكامل', 'مشاركة PDF', 'تذكيرات الصيانة', 'تحليل ذو أولوية']],
                    'features' => ['full_diagnosis' => true, 'advanced_obd' => true, 'full_history' => true, 'pdf_sharing' => true, 'maintenance_reminders' => true, 'priority_analysis' => true],
                ],
                'PLUS_YEARLY' => [
                    'type' => 'subscription', 'sort_order' => 40, 'recommended' => false,
                    'default_for_new_users' => false, 'max_vehicles' => 3, 'reports_per_period' => 4,
                    'en' => ['AutoMind Plus Yearly', 'The same Plus benefits with annual billing.', ['Up to three vehicles', 'Four full reports per billing period', 'Advanced OBD features', 'Full history', 'PDF sharing', 'Maintenance reminders', 'Priority analysis']],
                    'ar' => ['أوتومايند بلس سنوي', 'مزايا بلس نفسها مع فوترة سنوية.', ['حتى ثلاث سيارات', 'أربعة تقارير كاملة لكل فترة فوترة', 'ميزات OBD متقدمة', 'السجل الكامل', 'مشاركة PDF', 'تذكيرات الصيانة', 'تحليل ذو أولوية']],
                    'features' => ['full_diagnosis' => true, 'advanced_obd' => true, 'full_history' => true, 'pdf_sharing' => true, 'maintenance_reminders' => true, 'priority_analysis' => true],
                ],
            ];

            foreach ($plans as $code => $definition) {
                $plan = BillingPlan::query()->updateOrCreate(['code' => $code], [
                    'type' => $definition['type'], 'active' => true, 'published' => true,
                    'sort_order' => $definition['sort_order'], 'recommended' => $definition['recommended'],
                    'badge' => $definition['recommended'] ? 'recommended' : null,
                    'default_for_new_users' => $definition['default_for_new_users'],
                    'max_vehicles' => $definition['max_vehicles'], 'reports_per_period' => $definition['reports_per_period'],
                ]);
                foreach (['en', 'ar'] as $locale) {
                    [$name, $description, $features] = $definition[$locale];
                    $plan->localizations()->updateOrCreate(['locale' => $locale], [
                        'display_name' => $name, 'short_description' => $description,
                        'full_description' => $description, 'badge_text' => $definition['recommended'] ? ($locale === 'ar' ? 'موصى به' : 'Recommended') : null,
                        'feature_copy_json' => $features,
                    ]);
                }
                foreach ($definition['features'] as $feature => $enabled) {
                    $plan->features()->updateOrCreate(['feature_key' => $feature], ['enabled' => $enabled]);
                }
                foreach (['EG', 'SA', 'AE'] as $country) {
                    $plan->regions()->updateOrCreate(['country_code' => $country], ['visible' => true, 'paywall_variant' => 'default']);
                }
            }

            $mappings = [
                ['SINGLE_FULL_REPORT', 'apple', config('billing.products.single_report.apple'), 'consumable', null],
                ['SINGLE_FULL_REPORT', 'google', config('billing.products.single_report.google'), 'consumable', null],
                ['PLUS_MONTHLY', 'apple', config('billing.products.plus_monthly.apple'), 'subscription', null],
                ['PLUS_MONTHLY', 'google', config('billing.products.plus_monthly.google'), 'subscription', config('billing.products.plus_monthly.google_base_plan')],
                ['PLUS_YEARLY', 'apple', config('billing.products.plus_yearly.apple'), 'subscription', null],
                ['PLUS_YEARLY', 'google', config('billing.products.plus_yearly.google'), 'subscription', config('billing.products.plus_yearly.google_base_plan')],
            ];
            foreach (['sandbox', 'production'] as $environment) {
                foreach ($mappings as [$planCode, $platform, $productId, $type, $basePlanId]) {
                    $plan = BillingPlan::query()->where('code', $planCode)->sole();
                    $key = implode(':', [$platform, $environment, $productId, $basePlanId ?: '-', '-']);
                    $product = StoreProduct::query()->firstOrNew(['mapping_key' => $key]);
                    $product->fill([
                        'billing_plan_id' => $plan->id, 'platform' => $platform, 'product_id' => $productId,
                        'product_type' => $type, 'base_plan_id' => $basePlanId, 'environment' => $environment,
                    ]);
                    if (! $product->exists) {
                        // The Apple products are configured in App Store Connect
                        // and must remain queryable by StoreKit during review.
                        // Google mappings stay disabled until their Play Console
                        // setup is completed.
                        $product->active_for_sale = $platform === 'apple';
                        $product->store_status = $platform === 'apple' ? 'active' : 'pending';
                        $product->last_synced_at = $platform === 'apple' ? now() : null;
                    }
                    $product->save();
                }
            }
        });
    }
}
