<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'party_size_total',
        'party_size_players',
        'party_size_staff',
        'hotel_name',
        'training_ground',
        'flight_number',
        'arrival_date_time',
        'departure_date_time',
        'sc_liaison_name',
        'sc_liaison_phone',
        'flight_status',
        'actual_arrival_date_time',
        'actual_departure_date_time',
        'arrival_delay_minutes',
        'departure_delay_minutes',
    ];

    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('teams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->integer('party_size_total')->default(0);
            $table->integer('party_size_players')->default(0);
            $table->integer('party_size_staff')->default(0);
            $table->string('hotel_name')->nullable();
            $table->string('training_ground')->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->dateTime('arrival_date_time')->nullable();
            $table->dateTime('departure_date_time')->nullable();
            $table->string('sc_liaison_name')->nullable();
            $table->string('sc_liaison_phone', 50)->nullable();
            $table->string('flight_status')->nullable();
            $table->dateTime('actual_arrival_date_time')->nullable();
            $table->dateTime('actual_departure_date_time')->nullable();
            $table->integer('arrival_delay_minutes')->nullable();
            $table->integer('departure_delay_minutes')->nullable();
        });
    }
};
