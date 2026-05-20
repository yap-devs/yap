<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('level')->index();
            $table->string('name');
            $table->decimal('minimum_self_paid_amount', 10, 2)->default(0);
            $table->unsignedInteger('minimum_valid_referrals')->default(0);
            $table->decimal('commission_rate', 5, 4)->default(0);
            $table->string('status')->default('active')->comment('active, disabled');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_levels');
    }
};
