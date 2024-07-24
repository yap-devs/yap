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
        Schema::create('vmess_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('The name of the server');
            $table->string('server')->unique()->comment('The server address for the clash config `server` field');
            $table->unsignedSmallInteger('port')->comment('The port of the server for clash config `port` field');
            $table->unsignedTinyInteger('rate')->default(1)->comment('The rate of the server');
            $table->string('internal_server')->default('')->comment('Internal server address for the v2bridge');
            $table->unsignedTinyInteger('enabled')->default(1)->comment('Whether the server is enabled');
            $table->unsignedTinyInteger('for_low_priority')->default(0)->comment('Whether the server is for low priority users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vmess_servers');
    }
};
