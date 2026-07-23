<?php

namespace App\Models;

class VehicleMaintenanceRecord extends UlidModel
{
    protected function casts(): array
    {
        return ['service_date' => 'date', 'next_due_date' => 'date'];
    }
}
