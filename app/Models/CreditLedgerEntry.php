<?php

namespace App\Models;

class CreditLedgerEntry extends UlidModel
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer'];
    }
}
