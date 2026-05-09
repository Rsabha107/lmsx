<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Events table already exists with: id, name, event_logo, active_flag, created_by, updated_by
// This migration adds the missing platform fields.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('short_name', 100)->nullable()->after('name');
            $table->string('host_country', 10)->nullable()->after('short_name');
            $table->date('start_date')->nullable()->after('host_country');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('status', 20)->default('upcoming')->after('end_date');
            $table->text('notes')->nullable()->after('status');

            $table->foreign('host_country')->references('country_code')->on('countries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['host_country']);
            $table->dropColumn(['short_name', 'host_country', 'start_date', 'end_date', 'status', 'notes']);
        });
    }
};
