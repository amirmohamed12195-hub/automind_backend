<?php

namespace Database\Factories;

use App\Models\DiagnosticSession;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiagnosticSession> */
class DiagnosticSessionFactory extends Factory
{
    protected $model = DiagnosticSession::class;

    public function definition(): array
    {
        $vehicle = Vehicle::factory()->create();

        return ['user_id' => $vehicle->user_id, 'vehicle_id' => $vehicle->id, 'status' => 'draft', 'description' => 'The engine shakes while idling.', 'input_locale' => 'en', 'report_locale' => 'en', 'market_country_code' => 'EG', 'market_city' => 'Cairo', 'market_currency' => 'EGP', 'current_step' => 'preparingData', 'prompt_version' => 'diagnostic-v1', 'consent_version' => 'privacy-v1', 'consented_at' => now()];
    }
}
