<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Code-defined business setting');
            $table->text('value')->comment('Validated decimal value');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('setting_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->index()->comment('Changed setting key');
            $table->text('old_value')->nullable()->comment('Previous effective value');
            $table->text('new_value')->comment('New effective value');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Operator; null for initialization');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_changes');
        Schema::dropIfExists('settings');
    }
};
