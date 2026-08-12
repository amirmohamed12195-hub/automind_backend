<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class PlatformSettings
{
    /** @return array<string, array{group: string, label: string, type: string, value: mixed, description: string}> */
    public function definitions(): array
    {
        return [
            'registration_enabled' => ['group' => 'Access', 'label' => 'New registrations', 'type' => 'boolean', 'value' => true, 'description' => 'Allow new email and social accounts to be created.'],
            'diagnostics_enabled' => ['group' => 'Features', 'label' => 'AI diagnostics', 'type' => 'boolean', 'value' => true, 'description' => 'Allow users to start or retry diagnostic analysis.'],
            'appointments_enabled' => ['group' => 'Features', 'label' => 'Workshop appointments', 'type' => 'boolean', 'value' => true, 'description' => 'Allow users to create new workshop bookings.'],
            'maintenance_banner' => ['group' => 'Operations', 'label' => 'Maintenance message', 'type' => 'text', 'value' => '', 'description' => 'Optional message displayed to administrators during planned maintenance.'],
            'support_email' => ['group' => 'Customer care', 'label' => 'Support email', 'type' => 'email', 'value' => (string) config('public.support_email', 'support@automind.app'), 'description' => 'Public contact address used by the support team.'],
            'default_country' => ['group' => 'Localization', 'label' => 'Default country', 'type' => 'country', 'value' => 'EG', 'description' => 'Two-letter country used when a user has no market preference.'],
            'default_currency' => ['group' => 'Localization', 'label' => 'Default currency', 'type' => 'currency', 'value' => 'EGP', 'description' => 'Three-letter currency used for new market estimates.'],
            'default_locale' => ['group' => 'Localization', 'label' => 'Default language', 'type' => 'locale', 'value' => 'en', 'description' => 'Fallback language for new accounts and operational messages.'],
        ];
    }

    /** @return array<string, array{group: string, label: string, type: string, value: mixed, description: string}> */
    public function all(): array
    {
        $definitions = $this->definitions();
        if (! Schema::hasTable('platform_settings')) {
            return $definitions;
        }

        foreach (PlatformSetting::query()->get() as $setting) {
            if (isset($definitions[$setting->key])) {
                $definitions[$setting->key]['value'] = $setting->value;
            }
        }

        return $definitions;
    }

    public function get(string $key): mixed
    {
        $definition = $this->definitions()[$key] ?? null;
        if ($definition === null || ! Schema::hasTable('platform_settings')) {
            return $definition['value'] ?? null;
        }

        return PlatformSetting::query()->firstOrNew(
            ['key' => $key],
            ['value' => $definition['value']],
        )->value;
    }
}
