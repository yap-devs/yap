<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_levels', function (Blueprint $table) {
            $table->unsignedInteger('maximum_referral_codes')
                ->default(1)
                ->after('commission_rate')
                ->comment('Maximum active referral codes, including the system code');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_levels', function (Blueprint $table) {
            $table->dropColumn('maximum_referral_codes');
        });
    }
};
