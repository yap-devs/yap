<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([0 => 1, 1 => 2, 2 => 3, 3 => 5, 4 => 8, 5 => 13] as $level => $maximum_referral_codes) {
            DB::table('affiliate_levels')
                ->where('level', $level)
                ->update(['maximum_referral_codes' => $maximum_referral_codes]);
        }
    }

    public function down(): void
    {
        // Use a forward migration; administrators may have changed these limits after deployment.
    }
};
