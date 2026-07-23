<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\VehicleMake;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class VehicleCatalogController
{
    public function makes()
    {
        $locale = app()->getLocale();

        return ApiResponse::success(VehicleMake::query()->where('active', true)->orderBy("name_$locale")->get()->map(fn ($make) => ['id' => (string) $make->id, 'code' => $make->code, 'name' => $make->{"name_$locale"}])->all());
    }

    public function models(Request $request, string $makeCode)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:1886,'.((int) date('Y') + 1)]]);
        $make = VehicleMake::query()->where('code', $makeCode)->where('active', true)->firstOrFail();
        $locale = app()->getLocale();
        $query = $make->models()->where('active', true);
        if ($request->filled('year')) {
            $query->where(fn ($q) => $q->whereNull('start_year')->orWhere('start_year', '<=', $request->integer('year')))->where(fn ($q) => $q->whereNull('end_year')->orWhere('end_year', '>=', $request->integer('year')));
        }

        return ApiResponse::success($query->orderBy("name_$locale")->get()->map(fn ($model) => ['id' => (string) $model->id, 'code' => $model->code, 'name' => $model->{"name_$locale"}, 'startYear' => $model->start_year, 'endYear' => $model->end_year])->all());
    }
}
