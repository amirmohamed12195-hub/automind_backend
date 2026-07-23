<?php

namespace App\Models;

class WebhookReceipt extends UlidModel
{
    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
