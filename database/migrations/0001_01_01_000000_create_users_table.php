<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->decimal('balance')->default(0);
            $table->string('uuid')->default('');
            $table->unsignedBigInteger('traffic_downlink')->default(0)->comment('in bytes (total)');
            $table->unsignedBigInteger('traffic_uplink')->default(0)->comment('in bytes (total)');
            $table->unsignedBigInteger('traffic_unpaid')->default(0)->comment('in bytes (unsettled)');
            $table->timestamp('last_settled_at')->nullable()->comment('last time the unpaid traffic was settled');
            $table->unsignedBigInteger('github_id')->unique()->nullable();
            $table->string('github_nickname')->default('');
            $table->string('github_token')->default('');
            $table->timestamp('github_created_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
