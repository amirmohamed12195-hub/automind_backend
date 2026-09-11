<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleModelGeneration;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class VehicleCatalogController
{
    public function makes()
    {
        $locale = app()->getLocale();

        return ApiResponse::success(VehicleMake::query()
            ->where('active', true)
            ->withCount(['models' => fn ($query) => $query->where('active', true)])
            ->orderBy("name_$locale")
            ->get()
            ->map(fn (VehicleMake $make) => [
                'id' => (string) $make->id,
                'code' => $make->code,
                'name' => $make->{"name_$locale"},
                'logoUrl' => $make->logoUrl(),
                'hasModels' => $make->models_count > 0,
                'modelCount' => (int) $make->models_count,
                'allowsCustomModel' => true,
            ])->all());
    }

    public function models(Request $request, string $makeCode)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:1886,'.((int) date('Y') + 1)]]);
        $make = VehicleMake::query()->where('code', $makeCode)->where('active', true)->firstOrFail();
        $locale = app()->getLocale();
        $query = $make->models()->where('active', true)->with('generations');
        if ($request->filled('year')) {
            $query->where(fn ($q) => $q->whereNull('start_year')->orWhere('start_year', '<=', $request->integer('year')))->where(fn ($q) => $q->whereNull('end_year')->orWhere('end_year', '>=', $request->integer('year')));
        }

        return ApiResponse::success(
            $query->orderBy("name_$locale")->get()->map(function (VehicleModel $model) use ($locale, $make, $request): array {
                $display = $model->displayImage($request->filled('year') ? $request->integer('year') : null, $make);

                return [
                    'id' => (string) $model->id,
                    'code' => $model->code,
                    'name' => $model->{"name_$locale"},
                    'startYear' => $model->start_year,
                    'endYear' => $model->end_year,
                    'imageUrl' => $display['url'],
                    'imageType' => $display['type'],
                    'generationCode' => $display['generation']?->code,
                    'generationCount' => $model->generations->count(),
                    'imageAttribution' => $display['attribution'],
                ];
            })->all(),
            meta: ['customModelAllowed' => true],
        );
    }

    public function generations(Request $request, string $makeCode, string $modelCode)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:1886,'.((int) date('Y') + 1)]]);
        $make = VehicleMake::query()->where('code', $makeCode)->where('active', true)->firstOrFail();
        $model = $make->models()->where('code', $modelCode)->where('active', true)->firstOrFail();
        $query = $model->generations();
        if ($request->filled('year')) {
            $year = $request->integer('year');
            $query->where(fn ($q) => $q->whereNull('start_year')->orWhere('start_year', '<=', $year))
                ->where(fn ($q) => $q->whereNull('end_year')->orWhere('end_year', '>=', $year));
        }

        return ApiResponse::success($query
            ->orderByDesc('start_year')
            ->orderBy('name')
            ->get()
            ->map(function (VehicleModelGeneration $generation) use ($make): array {
                $imageUrl = $generation->imageUrl();

                return [
                    'id' => (string) $generation->id,
                    'code' => $generation->code,
                    'name' => $generation->name,
                    'startYear' => $generation->start_year,
                    'endYear' => $generation->end_year,
                    'bodyType' => $generation->body_type,
                    'imageUrl' => $imageUrl ?? $make->logoUrl(),
                    'imageType' => $imageUrl ? 'model_photo' : 'brand_logo',
                    'imageAttribution' => $generation->attribution(),
                ];
            })->all());
    }
}
