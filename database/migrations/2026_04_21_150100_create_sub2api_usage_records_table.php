<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub2api_usage_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Local user ID');
            $table->unsignedBigInteger('remote_usage_id')->unique()->comment('Sub2API usage ID');
            $table->string('remote_request_id')->nullable()->comment('Sub2API request ID');
            $table->unsignedBigInteger('remote_api_key_id')->comment('Sub2API API key ID');
            $table->string('model')->nullable()->comment('Requested model');
            $table->decimal('amount')->comment('Actual usage cost');
            $table->timestamp('usage_created_at')->nullable()->comment('Sub2API usage created at');
            $table->longText('payload')->comment('Raw Sub2API usage payload');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub2api_usage_records');
    }
};
