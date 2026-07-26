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
        Schema::create('pma_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->text('value');
            $table->string('scope')->default('global')->index(); // 'global', 'event', 'template'
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->timestamps();
            
            // Ensure unique combination of key, scope, and scope_id
            $table->unique(['key', 'scope', 'scope_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pma_settings');
    }
};
