<?php

namespace App\Models;

class BillingEvent extends UlidModel
{
    protected function casts(): array
    {
        return [
            'encrypted_payload_reference' => 'encrypted:array', 'attempts' => 'integer',
            'received_at' => 'datetime', 'processed_at' => 'datetime',
        ];
    }
}
