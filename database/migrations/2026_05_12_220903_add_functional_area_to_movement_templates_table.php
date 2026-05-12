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
        Schema::table('movement_templates', function (Blueprint $table) {
            $table->string('functional_area')->nullable()->after('scenario_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movement_templates', function (Blueprint $table) {
            $table->dropColumn('functional_area');
        });
    }
};
