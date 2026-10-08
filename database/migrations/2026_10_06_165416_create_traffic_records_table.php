<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('traffic_batch_id')->comment('Committed receipt batch');
            $table->unsignedBigInteger('user_id')->comment('Charged user');
            $table->unsignedBigInteger('node_route_id')->comment('Stable billing route');
            $table->unsignedBigInteger('raw_uplink')->comment('Original uploaded bytes');
            $table->unsignedBigInteger('raw_downlink')->comment('Original downloaded bytes');
            $table->decimal('applied_rate', 8, 2)->comment('Multiplier at first receipt');
            $table->unsignedBigInteger('billed_uplink')->comment('Uploaded bytes after multiplier and flooring');
            $table->unsignedBigInteger('billed_downlink')->comment('Downloaded bytes after multiplier and flooring');
            $table->unique(['traffic_batch_id', 'user_id', 'node_route_id'], 'traffic_records_identity_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['node_route_id', 'created_at']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_records');
    }
};
