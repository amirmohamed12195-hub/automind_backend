<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'engine' => '1.6L', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'mileage_km' => fake()->numberBetween(1000, 250000), 'health_score' => fake()->numberBetween(60, 100)];
    }
}
