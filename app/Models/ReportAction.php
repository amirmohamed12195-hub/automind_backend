<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportAction extends UlidModel
{
    protected function casts(): array
    {
        return ['professional_required' => 'boolean'];
    }

    /** @return HasMany<ReportActionTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ReportActionTranslation::class);
    }
}
