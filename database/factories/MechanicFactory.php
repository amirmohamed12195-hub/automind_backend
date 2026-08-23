<?php

namespace Database\Factories;

use App\Models\Mechanic;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mechanic> */
class MechanicFactory extends Factory
{
    protected $model = Mechanic::class;

    public function definition(): array
    {
        return ['name_en' => fake()->company(), 'name_ar' => 'مركز صيانة', 'description_en' => 'Verified workshop', 'description_ar' => 'ورشة موثقة', 'address' => fake()->streetAddress(), 'city' => 'Cairo', 'country_code' => 'EG', 'timezone' => 'Africa/Cairo', 'latitude' => 30.0444, 'longitude' => 31.2357, 'rating_average' => 4.5, 'rating_count' => 10, 'verified' => true, 'active' => true, 'working_hours_json' => [
            'sun' => ['00:00', '23:59'], 'mon' => ['00:00', '23:59'], 'tue' => ['00:00', '23:59'],
            'wed' => ['00:00', '23:59'], 'thu' => ['00:00', '23:59'], 'fri' => ['00:00', '23:59'],
            'sat' => ['00:00', '23:59'],
        ]];
    }
}
