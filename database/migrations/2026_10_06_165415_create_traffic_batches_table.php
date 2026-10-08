<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('node_id')->comment('Reporting node');
            $table->uuid('batch_uuid')->comment('Immutable agent-generated retry identity');
            $table->char('payload_hash', 64)->comment('SHA-256 of canonical batch contents');
            $table->timestamp('received_at')->comment('First committed receipt time');
            $table->timestamp('aggregated_at')->nullable()->comment('Display statistics aggregation completion');
            $table->unique(['node_id', 'batch_uuid']);
            $table->index(['aggregated_at', 'received_at', 'id'], 'traffic_batches_pending_index');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_batches');
    }
};
