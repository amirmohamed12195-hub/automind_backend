<?php

namespace App\Models;

class ServiceEstimateLineItem extends UlidModel
{
    protected function casts(): array
    {
        return ['source_confidence_metadata' => 'array'];
    }
}
