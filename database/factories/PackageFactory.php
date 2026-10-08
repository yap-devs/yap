<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => null,
            'price' => '5.00',
            'duration_days' => 30,
            'traffic_limit' => 10737418240,
            'status' => Package::STATUS_ACTIVE,
        ];
    }
}
