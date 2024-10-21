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
        Schema::create('relay_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('The name of the server');
            $table->string('server')->comment('The server address for the clash config `server` field');
            $table->unsignedSmallInteger('port')->comment('The port of the server for clash config `port` field');
            $table->unsignedTinyInteger('rate')->default(1)->comment('The rate of the server');
            $table->unsignedTinyInteger('enabled')->default(1)->comment('Whether the server is enabled');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relay_servers');
    }
};
