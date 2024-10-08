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
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('The ID of the user');
            $table->unsignedBigInteger('traffic_downlink')->default(0)->comment('in bytes');
            $table->unsignedBigInteger('traffic_uplink')->default(0)->comment('in bytes');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
