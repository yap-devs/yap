<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referral_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promoter_id')->index();
            $table->string('code')->unique();
            $table->string('type')->default('custom')->comment('system, custom');
            $table->string('status')->default('active')->comment('active, disabled');
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['promoter_id', 'status', 'disabled_at'],
                'affiliate_referral_codes_availability_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referral_codes');
    }
};
