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
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('functional_area', ['LOG', 'AND', 'MOB'])->nullable()->after('movement_template_id');
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->enum('functional_area', ['LOG', 'AND', 'MOB'])->nullable()->after('kind');
        });

        Schema::table('jobs_operations', function (Blueprint $table) {
            $table->enum('functional_area', ['LOG', 'AND', 'MOB'])->nullable()->after('movement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('functional_area');
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->dropColumn('functional_area');
        });

        Schema::table('jobs_operations', function (Blueprint $table) {
            $table->dropColumn('functional_area');
        });
    }
};
