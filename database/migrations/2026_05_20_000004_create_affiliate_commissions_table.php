<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_id')->index();
            $table->unsignedBigInteger('promoter_id')->index();
            $table->unsignedBigInteger('referrer_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->index();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('affiliate_level')->default(0);
            $table->decimal('base_amount', 10, 2);
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending')->comment('pending, credited, rejected, reversed');
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->unsignedBigInteger('credited_balance_detail_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['referral_id', 'source_type', 'source_id'], 'affiliate_commissions_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
