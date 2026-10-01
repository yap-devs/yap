<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_packages', function (Blueprint $table): void {
            $table->index(['user_id', 'deleted_at', 'created_at', 'id'], 'user_packages_user_history_index');
        });
        Schema::table('affiliate_referrals', function (Blueprint $table): void {
            $table->index(['promoter_id', 'deleted_at', 'created_at', 'id'], 'affiliate_referrals_promoter_history_index');
        });
        Schema::table('affiliate_commissions', function (Blueprint $table): void {
            $table->index(['promoter_id', 'deleted_at', 'created_at', 'id'], 'affiliate_commissions_promoter_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table): void {
            $table->dropIndex('affiliate_commissions_promoter_history_index');
        });
        Schema::table('affiliate_referrals', function (Blueprint $table): void {
            $table->dropIndex('affiliate_referrals_promoter_history_index');
        });
        Schema::table('user_packages', function (Blueprint $table): void {
            $table->dropIndex('user_packages_user_history_index');
        });
    }
};
