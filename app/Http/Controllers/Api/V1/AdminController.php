<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MechanicResource;
use App\Jobs\AnalyzeDiagnosticSession;
use App\Jobs\RefreshServiceEstimate;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\CurrencyRate;
use App\Models\DiagnosticSession;
use App\Models\LaborRateSource;
use App\Models\MaintenanceServiceDefinition;
use App\Models\Mechanic;
use App\Models\MechanicSpecialty;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Services\Notifications\UserNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController
{
    public function mechanics(Request $request)
    {
        $limit = $request->validate(['limit' => ['sometimes', 'integer', 'between:1,100']])['limit'] ?? 30;

        return ApiResponse::success(MechanicResource::collection(Mechanic::query()->with('specialties')->latest()->paginate($limit)->items())->resolve());
    }

    public function storeMechanic(Request $request)
    {
        $mechanic = Mechanic::query()->create($this->mechanicData($request, true));
        $this->syncMechanicSpecialties($request, $mechanic);
        $this->audit($request, 'admin.mechanic.created', $mechanic);

        return ApiResponse::success((new MechanicResource($mechanic->load('specialties')))->resolve(), 201);
    }

    public function updateMechanic(Request $request, Mechanic $mechanic)
    {
        $mechanic->update($this->mechanicData($request, false));
        $this->syncMechanicSpecialties($request, $mechanic);
        $this->audit($request, 'admin.mechanic.updated', $mechanic);

        return ApiResponse::success((new MechanicResource($mechanic->fresh()->load('specialties')))->resolve());
    }

    public function deleteMechanic(Request $request, Mechanic $mechanic)
    {
        $mechanic->update(['active' => false]);
        $this->audit($request, 'admin.mechanic.disabled', $mechanic);

        return response()->noContent();
    }

    public function verifyMechanic(Request $request, Mechanic $mechanic)
    {
        $data = $request->validate(['verified' => ['required', 'boolean']]);
        $mechanic->update(['verified' => $data['verified']]);
        $this->audit($request, 'admin.mechanic.verification_changed', $mechanic);

        return ApiResponse::success((new MechanicResource($mechanic))->resolve());
    }

    public function storeMake(Request $request)
    {
        $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:80', 'unique:vehicle_makes,code'], 'nameEn' => ['required', 'string', 'max:120'], 'nameAr' => ['required', 'string', 'max:120'], 'active' => ['sometimes', 'boolean']]);
        $make = VehicleMake::query()->create(['code' => strtolower($data['code']), 'name_en' => $data['nameEn'], 'name_ar' => $data['nameAr'], 'active' => $data['active'] ?? true]);
        $this->audit($request, 'admin.vehicle_make.created', $make);

        return ApiResponse::success(['id' => (string) $make->id, 'code' => $make->code], 201);
    }

    public function updateMake(Request $request, VehicleMake $make)
    {
        if ($request->exists('code')) {
            $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        }
        $data = $request->validate([
            'code' => ['sometimes', 'alpha_dash', 'max:80', Rule::unique('vehicle_makes', 'code')->ignore($make->id)],
            'nameEn' => ['sometimes', 'string', 'max:120'], 'nameAr' => ['sometimes', 'string', 'max:120'], 'active' => ['sometimes', 'boolean'],
        ]);
        $make->update(array_filter(['code' => $data['code'] ?? null, 'name_en' => $data['nameEn'] ?? null, 'name_ar' => $data['nameAr'] ?? null, 'active' => $data['active'] ?? null], fn ($v) => $v !== null));
        $this->audit($request, 'admin.vehicle_make.updated', $make);

        return ApiResponse::success(['id' => (string) $make->id, 'code' => $make->code]);
    }

    public function storeModel(Request $request, VehicleMake $make)
    {
        $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:100', Rule::unique('vehicle_models', 'code')->where('make_id', $make->id)], 'nameEn' => ['required', 'string', 'max:120'], 'nameAr' => ['required', 'string', 'max:120'], 'startYear' => ['nullable', 'integer', 'between:1886,2200'], 'endYear' => ['nullable', 'integer', 'gte:startYear'], 'active' => ['sometimes', 'boolean']]);
        $model = VehicleModel::query()->create(['make_id' => $make->id, 'code' => strtolower($data['code']), 'name_en' => $data['nameEn'], 'name_ar' => $data['nameAr'], 'start_year' => $data['startYear'] ?? null, 'end_year' => $data['endYear'] ?? null, 'active' => $data['active'] ?? true]);
        $this->audit($request, 'admin.vehicle_model.created', $model);

        return ApiResponse::success(['id' => (string) $model->id, 'code' => $model->code], 201);
    }

    public function serviceDefinitions()
    {
        return ApiResponse::success(MaintenanceServiceDefinition::query()->orderBy('code')->get()->map(fn ($s) => ['id' => (string) $s->id, 'code' => $s->code, 'nameEn' => $s->name_en, 'nameAr' => $s->name_ar, 'defaultMonthInterval' => $s->default_month_interval, 'defaultKmInterval' => $s->default_km_interval, 'active' => (bool) $s->active])->all());
    }

    public function storeServiceDefinition(Request $request)
    {
        $service = MaintenanceServiceDefinition::query()->create($this->serviceData($request, true));
        $this->audit($request, 'admin.maintenance_service.created', $service);

        return ApiResponse::success(['id' => (string) $service->id, 'code' => $service->code], 201);
    }

    public function updateServiceDefinition(Request $request, MaintenanceServiceDefinition $service)
    {
        $service->update($this->serviceData($request, false, $service));
        $this->audit($request, 'admin.maintenance_service.updated', $service);

        return ApiResponse::success(['id' => (string) $service->id, 'code' => $service->code]);
    }

    public function broadcast(Request $request, UserNotificationService $notifications)
    {
        $data = $request->validate(['titleEn' => ['required', 'string', 'max:160'], 'titleAr' => ['required', 'string', 'max:160'], 'bodyEn' => ['required', 'string', 'max:1000'], 'bodyAr' => ['required', 'string', 'max:1000'], 'userIds' => ['nullable', 'array', 'max:10000'], 'userIds.*' => ['ulid', 'exists:users,id'], 'data' => ['nullable', 'array']]);
        $ids = $data['userIds'] ?? User::query()->pluck('id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (User::query()->whereIn('id', $chunk)->get(['id', 'locale']) as $user) {
                $notifications->send($user, 'admin_broadcast', $data['titleEn'], $data['titleAr'], $data['bodyEn'], $data['bodyAr'], $data['data'] ?? []);
            }
        }
        AuditLog::query()->create(['actor_user_id' => $request->user()->id, 'action' => 'admin.notification.broadcast', 'request_id' => ApiResponse::requestId(), 'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 'user_agent_summary' => mb_substr((string) $request->userAgent(), 0, 255), 'metadata_json' => ['recipientCount' => count($ids)]]);

        return ApiResponse::success(['recipientCount' => count($ids)], 202);
    }

    public function failedAiRuns(Request $request)
    {
        $limit = $request->validate(['limit' => ['sometimes', 'integer', 'between:1,100']])['limit'] ?? 30;
        $runs = AiRun::query()->where('status', 'failed')->latest()->paginate($limit);

        return ApiResponse::success(collect($runs->items())->map(fn ($run) => ['id' => (string) $run->id, 'sessionId' => (string) $run->diagnostic_session_id, 'taskType' => $run->task_type, 'provider' => $run->provider, 'endpoint' => $run->endpoint, 'model' => $run->model, 'category' => $run->safe_error_category, 'message' => $run->safe_error_message, 'createdAt' => $run->created_at?->utc()->toIso8601ZuluString()])->all());
    }

    public function retryAiRun(Request $request, AiRun $run)
    {
        if ($run->status !== 'failed') {
            return ApiResponse::error('AI_RUN_NOT_RETRYABLE', __('api.invalid_transition'), 409);
        }
        $session = DiagnosticSession::query()->findOrFail($run->diagnostic_session_id);
        if ($run->task_type === 'price_research' && $session->report) {
            RefreshServiceEstimate::dispatch($session->report->id)->afterCommit();
        } else {
            $updated = DiagnosticSession::query()->whereKey($session->id)->where('status', 'failed')->update(['status' => 'queued', 'error_code' => null, 'safe_error_message' => null, 'updated_at' => now()]);
            if ($updated !== 1) {
                return ApiResponse::error('AI_RUN_NOT_RETRYABLE', __('api.invalid_transition'), 409);
            }
            AnalyzeDiagnosticSession::dispatch($session->id)->afterCommit();
        }
        $this->audit($request, 'admin.ai_run.retried', $run);

        return ApiResponse::success(['sessionId' => (string) $session->id, 'status' => 'queued'], 202);
    }

    public function laborRates(Request $request)
    {
        $data = $request->validate(['countryCode' => ['nullable', 'string', 'size:2'], 'city' => ['nullable', 'string', 'max:120'], 'serviceCategory' => ['nullable', 'string', 'max:120'], 'limit' => ['nullable', 'integer', 'between:1,100']]);
        $rates = LaborRateSource::query()
            ->when(isset($data['countryCode']), fn ($query) => $query->where('country_code', strtoupper($data['countryCode'])))
            ->when(isset($data['city']), fn ($query) => $query->where('city', $data['city']))
            ->when(isset($data['serviceCategory']), fn ($query) => $query->where('service_category', $data['serviceCategory']))
            ->latest('observed_at')->limit($data['limit'] ?? 30)->get();

        return ApiResponse::success($rates->map(fn (LaborRateSource $rate) => $this->laborRateResource($rate))->all());
    }

    public function storeLaborRate(Request $request)
    {
        $data = $request->validate([
            'countryCode' => ['required', 'string', 'size:2', 'alpha'], 'city' => ['nullable', 'string', 'max:120'],
            'serviceCategory' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'hourlyLow' => ['required', 'decimal:0,2', 'gt:0'], 'hourlyTypical' => ['required', 'decimal:0,2', 'gt:0'], 'hourlyHigh' => ['required', 'decimal:0,2', 'gt:0'],
            'hoursLow' => ['required', 'decimal:0,2', 'gt:0'], 'hoursTypical' => ['required', 'decimal:0,2', 'gt:0'], 'hoursHigh' => ['required', 'decimal:0,2', 'gt:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'], 'observedAt' => ['required', 'date', 'before_or_equal:now'], 'expiresAt' => ['nullable', 'date', 'after:observedAt'],
        ]);
        $this->orderedRange($data, 'hourly');
        $this->orderedRange($data, 'hours');
        $rate = LaborRateSource::query()->create([
            'administrator_user_id' => $request->user()->id, 'country_code' => strtoupper($data['countryCode']), 'city' => $data['city'] ?? null,
            'service_category' => $data['serviceCategory'], 'hourly_low' => $data['hourlyLow'], 'hourly_typical' => $data['hourlyTypical'], 'hourly_high' => $data['hourlyHigh'],
            'hours_low' => $data['hoursLow'], 'hours_typical' => $data['hoursTypical'], 'hours_high' => $data['hoursHigh'],
            'currency' => strtoupper($data['currency']), 'observed_at' => $data['observedAt'], 'expires_at' => $data['expiresAt'] ?? null,
        ]);
        $this->audit($request, 'admin.labor_rate.created', $rate);

        return ApiResponse::success($this->laborRateResource($rate), 201);
    }

    public function currencyRates(Request $request)
    {
        $data = $request->validate(['baseCurrency' => ['nullable', 'string', 'size:3', 'alpha'], 'quoteCurrency' => ['nullable', 'string', 'size:3', 'alpha'], 'limit' => ['nullable', 'integer', 'between:1,100']]);
        $rates = CurrencyRate::query()
            ->when(isset($data['baseCurrency']), fn ($query) => $query->where('base_currency', strtoupper($data['baseCurrency'])))
            ->when(isset($data['quoteCurrency']), fn ($query) => $query->where('quote_currency', strtoupper($data['quoteCurrency'])))
            ->latest('effective_at')->limit($data['limit'] ?? 30)->get();

        return ApiResponse::success($rates->map(fn (CurrencyRate $rate) => $this->currencyRateResource($rate))->all());
    }

    public function storeCurrencyRate(Request $request)
    {
        $data = $request->validate([
            'baseCurrency' => ['required', 'string', 'size:3', 'alpha', 'different:quoteCurrency'], 'quoteCurrency' => ['required', 'string', 'size:3', 'alpha'],
            'rate' => ['required', 'decimal:0,10', 'gt:0'], 'provider' => ['required', 'string', 'max:120'], 'effectiveAt' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $rate = CurrencyRate::query()->create([
            'base_currency' => strtoupper($data['baseCurrency']), 'quote_currency' => strtoupper($data['quoteCurrency']),
            'rate' => $data['rate'], 'provider' => $data['provider'], 'effective_at' => $data['effectiveAt'],
        ]);
        $this->audit($request, 'admin.currency_rate.created', $rate);

        return ApiResponse::success($this->currencyRateResource($rate), 201);
    }

    private function mechanicData(Request $request, bool $create): array
    {
        $r = $create ? 'required' : 'sometimes';
        $d = $request->validate(['nameEn' => [$r, 'string', 'max:160'], 'nameAr' => [$r, 'string', 'max:160'], 'descriptionEn' => ['nullable', 'string', 'max:2000'], 'descriptionAr' => ['nullable', 'string', 'max:2000'], 'phone' => ['nullable', 'string', 'max:32'], 'email' => ['nullable', 'email:rfc'], 'address' => [$r, 'string', 'max:255'], 'city' => [$r, 'string', 'max:120'], 'countryCode' => [$r, 'string', 'size:2'], 'latitude' => [$r, 'numeric', 'between:-90,90'], 'longitude' => [$r, 'numeric', 'between:-180,180'], 'workingHours' => ['nullable', 'array'], 'specialtyCodes' => ['sometimes', 'array', 'max:20'], 'specialtyCodes.*' => ['string', 'distinct', 'exists:mechanic_specialties,code'], 'active' => ['sometimes', 'boolean']]);
        $map = ['nameEn' => 'name_en', 'nameAr' => 'name_ar', 'descriptionEn' => 'description_en', 'descriptionAr' => 'description_ar', 'phone' => 'phone', 'email' => 'email', 'address' => 'address', 'city' => 'city', 'countryCode' => 'country_code', 'latitude' => 'latitude', 'longitude' => 'longitude', 'workingHours' => 'working_hours_json', 'active' => 'active'];
        $out = [];
        foreach ($map as $k => $v) {
            if (array_key_exists($k, $d)) {
                $out[$v] = $k === 'countryCode' ? strtoupper($d[$k]) : $d[$k];
            }
        }

        return $out;
    }

    private function syncMechanicSpecialties(Request $request, Mechanic $mechanic): void
    {
        if (! $request->exists('specialtyCodes')) {
            return;
        }
        $ids = MechanicSpecialty::query()->whereIn('code', $request->input('specialtyCodes', []))->pluck('id');
        $mechanic->specialties()->sync($ids);
    }

    private function serviceData(Request $request, bool $create, ?MaintenanceServiceDefinition $current = null): array
    {
        if ($request->exists('code')) {
            $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        }
        $r = $create ? 'required' : 'sometimes';
        $d = $request->validate(['code' => [$r, 'alpha_dash', 'max:100', Rule::unique('maintenance_service_definitions', 'code')->ignore($current?->id)], 'nameEn' => [$r, 'string', 'max:160'], 'nameAr' => [$r, 'string', 'max:160'], 'descriptionEn' => ['nullable', 'string'], 'descriptionAr' => ['nullable', 'string'], 'defaultMonthInterval' => ['nullable', 'integer', 'between:1,240'], 'defaultKmInterval' => ['nullable', 'integer', 'between:1,1000000'], 'active' => ['sometimes', 'boolean']]);
        $map = ['code' => 'code', 'nameEn' => 'name_en', 'nameAr' => 'name_ar', 'descriptionEn' => 'description_en', 'descriptionAr' => 'description_ar', 'defaultMonthInterval' => 'default_month_interval', 'defaultKmInterval' => 'default_km_interval', 'active' => 'active'];
        $out = [];
        foreach ($map as $k => $v) {
            if (array_key_exists($k, $d)) {
                $out[$v] = $k === 'code' ? strtolower($d[$k]) : $d[$k];
            }
        }

        return $out;
    }

    private function orderedRange(array $data, string $prefix): void
    {
        $low = (string) $data[$prefix.'Low'];
        $typical = (string) $data[$prefix.'Typical'];
        $high = (string) $data[$prefix.'High'];
        if (bccomp($low, $typical, 2) > 0 || bccomp($typical, $high, 2) > 0) {
            throw ValidationException::withMessages([$prefix.'Typical' => [__('api.estimate_range_invalid')]]);
        }
    }

    private function laborRateResource(LaborRateSource $rate): array
    {
        return [
            'id' => (string) $rate->id, 'countryCode' => $rate->country_code, 'city' => $rate->city, 'serviceCategory' => $rate->service_category,
            'hourlyLow' => $rate->hourly_low, 'hourlyTypical' => $rate->hourly_typical, 'hourlyHigh' => $rate->hourly_high,
            'hoursLow' => $rate->hours_low, 'hoursTypical' => $rate->hours_typical, 'hoursHigh' => $rate->hours_high,
            'currency' => $rate->currency, 'observedAt' => $rate->observed_at?->utc()->toIso8601String(), 'expiresAt' => $rate->expires_at?->utc()->toIso8601String(),
        ];
    }

    private function currencyRateResource(CurrencyRate $rate): array
    {
        return [
            'id' => (string) $rate->id, 'baseCurrency' => $rate->base_currency, 'quoteCurrency' => $rate->quote_currency,
            'rate' => $rate->rate, 'provider' => $rate->provider, 'effectiveAt' => $rate->effective_at?->utc()->toIso8601String(),
        ];
    }

    private function audit(Request $request, string $action, $target): void
    {
        AuditLog::query()->create(['actor_user_id' => $request->user()->id, 'action' => $action, 'target_type' => $target::class, 'target_id' => $target->id, 'request_id' => ApiResponse::requestId(), 'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 'user_agent_summary' => mb_substr((string) $request->userAgent(), 0, 255)]);
    }
}
