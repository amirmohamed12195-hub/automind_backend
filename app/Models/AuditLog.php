<?php

namespace App\Models;

class AuditLog extends UlidModel
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }
}
