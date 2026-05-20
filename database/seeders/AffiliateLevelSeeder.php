<?php

namespace Database\Seeders;

use App\Models\AffiliateLevel;
use Illuminate\Database\Seeder;

class AffiliateLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['level' => 0, 'name' => 'Visitor', 'minimum_self_paid_amount' => 0, 'minimum_valid_referrals' => 0, 'commission_rate' => 0],
            ['level' => 1, 'name' => 'Starter', 'minimum_self_paid_amount' => 5, 'minimum_valid_referrals' => 0, 'commission_rate' => 0.10],
            ['level' => 2, 'name' => 'Partner', 'minimum_self_paid_amount' => 20, 'minimum_valid_referrals' => 3, 'commission_rate' => 0.15],
            ['level' => 3, 'name' => 'Growth', 'minimum_self_paid_amount' => 50, 'minimum_valid_referrals' => 10, 'commission_rate' => 0.20],
            ['level' => 4, 'name' => 'Pro', 'minimum_self_paid_amount' => 100, 'minimum_valid_referrals' => 30, 'commission_rate' => 0.25],
            ['level' => 5, 'name' => 'Elite', 'minimum_self_paid_amount' => 300, 'minimum_valid_referrals' => 100, 'commission_rate' => 0.30],
        ];

        foreach ($levels as $level) {
            AffiliateLevel::updateOrCreate(
                ['level' => $level['level']],
                [...$level, 'status' => AffiliateLevel::STATUS_ACTIVE],
            );
        }
    }
}
