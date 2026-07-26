<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This migration originally assumed a `venues` table already existed (created
// out-of-band, not via migration) and only added the fields below.
// It now also creates the base table if missing, so a fresh install works.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues')) {
            Schema::create('venues', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

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
