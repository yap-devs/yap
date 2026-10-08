<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('node_id')->index()->comment('Owning physical node');
            $table->string('name')->comment('Subscription route name');
            $table->string('server')->comment('Client-facing hostname or address');
            $table->unsignedSmallInteger('port')->comment('Client-facing port');
            $table->string('inbound_tag', 64)->comment('Core inbound handler tag');
            $table->unsignedSmallInteger('listen_port')->comment('Actual core listener port');
            $table->decimal('rate', 8, 2)->default('1.00')->comment('Traffic billing multiplier');
            $table->boolean('enabled')->default(false)->comment('Whether this route is enabled');
            $table->boolean('for_low_priority')->default(false)->comment('Whether low-priority users may use this route');
            $table->unsignedInteger('sort')->default(0)->comment('Subscription display order');
            $table->unique(['node_id', 'listen_port'], 'node_routes_entry_unique');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_routes');
    }
};
