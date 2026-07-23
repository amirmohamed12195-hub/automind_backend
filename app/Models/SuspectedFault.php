<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SuspectedFault extends UlidModel
{
    protected function casts(): array
    {
        return ['confidence' => 'float'];
    }

    /** @return HasMany<SuspectedFaultTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(SuspectedFaultTranslation::class);
    }

    /** @return HasMany<FaultCause, $this> */
    public function causes(): HasMany
    {
        return $this->hasMany(FaultCause::class)->orderBy('sort_order');
    }

    /** @return HasMany<ReportAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ReportAction::class)->orderBy('sort_order');
    }

    /** @return HasMany<PartRecommendation, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(PartRecommendation::class)->orderBy('sort_order');
    }

    /** @return HasMany<ReportEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(ReportEvidence::class);
    }
}
