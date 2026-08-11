<?php

namespace App\Models;

class BillingAdminAuditLog extends UlidModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'created_at' => 'datetime'];
    }
}
