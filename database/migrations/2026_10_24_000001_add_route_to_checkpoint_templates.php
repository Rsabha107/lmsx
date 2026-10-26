<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A checkpoint template's typical route, used to suggest it on movement template legs with the same From / To.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkpoint_templates', function (Blueprint $table) {
            $table->string('from_location')->nullable()->after('description');
            $table->string('to_location')->nullable()->after('from_location');
        });
    }

    public function down(): void
    {
        Schema::table('checkpoint_templates', function (Blueprint $table) {
            $table->dropColumn(['from_location', 'to_location']);
        });
    }
};
