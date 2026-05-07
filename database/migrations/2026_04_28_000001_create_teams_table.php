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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('team_name');
            $table->string('country_id', 10);
            $table->string('flag', 10)->nullable();
            $table->string('group_pool', 50)->nullable();
            $table->unsignedBigInteger('classification_type_id')->nullable();
            $table->integer('party_size_total')->default(0);
            $table->integer('party_size_players')->default(0);
            $table->integer('party_size_staff')->default(0);
            $table->string('hotel_name')->nullable();
            $table->string('training_ground')->nullable();
            $table->dateTime('arrival_date_time')->nullable();
            $table->dateTime('departure_date_time')->nullable();
            $table->string('head_of_delegation')->nullable();
            $table->string('sc_liaison_name')->nullable();
            $table->string('sc_liaison_phone', 50)->nullable();
            $table->string('bib_accent_color', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('country_id');
            $table->index('group_pool');
            
            $table->foreign('classification_type_id')
                ->references('id')
                ->on('team_classifications')
                ->nullOnDelete();
            
            $table->foreign('country_id')
                ->references('country_code')
                ->on('countries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
