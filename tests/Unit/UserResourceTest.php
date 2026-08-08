<?php

namespace Tests\Unit;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    public function test_missing_legacy_preferences_are_returned_as_safe_defaults(): void
    {
        $user = new User([
            'name' => 'Driver',
            'email' => 'driver@example.com',
        ]);
        $user->forceFill([
            'id' => '01JTESTUSER000000000000000',
            'locale' => null,
            'theme_mode' => null,
            'units' => null,
            'maintenance_reminders_enabled' => null,
        ]);

        $resource = (new UserResource($user))->toArray(
            Request::create('/api/v1/me'),
        );

        $this->assertSame('en', $resource['locale']);
        $this->assertSame('system', $resource['themeMode']);
        $this->assertSame('metric', $resource['units']);
        $this->assertTrue($resource['maintenanceRemindersEnabled']);
    }
}
