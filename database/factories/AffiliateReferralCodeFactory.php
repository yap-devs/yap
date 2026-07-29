<?php

namespace Database\Factories;

use App\Models\AffiliateReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateReferralCode>
 */
class AffiliateReferralCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promoter_id' => fake()->numberBetween(1, 100000),
            'code' => fake()->unique()->regexify('[a-z][a-z0-9-]{7}'),
            'type' => AffiliateReferralCode::TYPE_CUSTOM,
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
            'disabled_at' => null,
        ];
    }
}
