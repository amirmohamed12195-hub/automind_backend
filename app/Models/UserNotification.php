<?php

namespace App\Models;

class UserNotification extends UlidModel
{
    protected $table = 'notifications';

    protected function casts(): array
    {
        return ['data_json' => 'array', 'read_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
