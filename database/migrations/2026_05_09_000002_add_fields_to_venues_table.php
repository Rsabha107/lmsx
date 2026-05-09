<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->enum('type', ['stadium', 'training_ground', 'hotel', 'conference', 'other'])->default('stadium');
            $table->string('city')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('country_code', 3)->nullable();
            
            $table->foreign('country_code')->references('country_code')->on('countries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
            $table->dropColumn(['type', 'city', 'capacity', 'country_code']);
        });
    }
};
