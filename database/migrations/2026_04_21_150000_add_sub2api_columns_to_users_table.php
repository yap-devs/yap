<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('sub2api_key_id')->nullable()->after('uuid')->comment('Sub2API API key ID');
            $table->string('sub2api_key_status')->nullable()->after('sub2api_key_id')->comment('Sub2API key status');
            $table->unsignedBigInteger('sub2api_last_usage_id')->nullable()->after('sub2api_key_status')->comment('Last synced Sub2API usage ID');
            $table->timestamp('sub2api_last_synced_at')->nullable()->after('sub2api_last_usage_id')->comment('Last Sub2API usage sync time');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sub2api_key_id',
                'sub2api_key_status',
                'sub2api_last_usage_id',
                'sub2api_last_synced_at',
            ]);
        });
    }
};
