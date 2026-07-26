<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This migration originally assumed an `events` table already existed (created
// out-of-band, not via migration) and only added the platform fields below.
// It now also creates the base table if missing, so a fresh install works.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('event_logo')->nullable();
                $table->boolean('active_flag')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

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
