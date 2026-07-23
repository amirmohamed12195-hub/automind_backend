<?php

namespace App\Models;

class DeviceToken extends UlidModel
{
    protected function casts(): array
    {
        return ['push_token' => 'encrypted', 'enabled' => 'boolean', 'last_seen_at' => 'datetime'];
    }
}
