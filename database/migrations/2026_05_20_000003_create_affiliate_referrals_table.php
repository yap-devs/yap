<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promoter_id')->index();
            $table->unsignedBigInteger('referrer_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->unique();
            $table->string('code')->index();
            $table->string('status')->default('registered')->comment('registered, qualified, earning, expired, rejected, blocked');
            $table->string('landing_path')->nullable();
            $table->string('source')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->unsignedBigInteger('first_qualified_payment_id')->nullable();
            $table->decimal('first_qualified_payment_amount', 10, 2)->nullable();
            $table->timestamp('commission_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }
};
