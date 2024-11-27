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
            $table->unsignedBigInteger('vmess_server_id');
            $table->string('name');
            $table->string('server');
            $table->unsignedSmallInteger('port')->default(0);
            $table->unsignedTinyInteger('enabled')->default(1);
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
