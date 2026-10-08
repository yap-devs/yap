<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('traffic_source')->default('agent')->comment('Agent managed traffic');
            $table->string('name')->comment('Operator-facing node name');
            $table->char('agent_token_hash', 64)->nullable()->unique()->comment('SHA-256 digest of the random agent token');
            $table->boolean('enabled')->default(false)->comment('Whether the agent may connect');
            $table->unsignedBigInteger('desired_revision')->default(1)->comment('Desired authorization and configuration revision');
            $table->unsignedBigInteger('applied_revision')->default(0)->comment('Last revision applied by the agent');
            $table->timestamp('last_seen_at')->nullable()->comment('Last persisted heartbeat');
            $table->string('agent_version')->nullable()->comment('Reported agent version');
            $table->string('core_version')->nullable()->comment('Reported core version');
            $table->json('core_config')->nullable()->comment('Core settings without generated users or listener ports');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
