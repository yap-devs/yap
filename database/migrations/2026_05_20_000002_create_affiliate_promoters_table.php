<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_promoters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('code')->unique();
            $table->string('status')->default('active')->comment('active, blocked');
            $table->decimal('custom_commission_rate', 5, 4)->nullable();
            $table->unsignedInteger('total_valid_referrals')->default(0);
            $table->decimal('total_commission_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_promoters');
    }
};
