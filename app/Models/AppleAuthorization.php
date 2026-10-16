<?php

namespace App\Models;

class AppleAuthorization extends UlidModel
{
    protected $hidden = ['refresh_token'];

    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted', 'revocation_requested_at' => 'datetime', 'retry_at' => 'datetime'];
    }
}
