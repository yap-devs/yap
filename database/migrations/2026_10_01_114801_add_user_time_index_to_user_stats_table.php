<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('user_stats', 'user_stats_user_deleted_created_index')) {
            Schema::table('user_stats', function (Blueprint $table): void {
                $table->index(['user_id', 'deleted_at', 'created_at'], 'user_stats_user_deleted_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_stats', function (Blueprint $table): void {
            $table->dropIndex('user_stats_user_deleted_created_index');
        });
    }
};
