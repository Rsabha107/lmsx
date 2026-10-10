<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Client-generated operation ids let the mobile app replay queued writes without duplicating them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->string('client_op_id', 64)->nullable();
        });

        Schema::table('job_issues', function (Blueprint $table) {
            $table->string('client_op_id', 64)->nullable();
            $table->unique(['job_id', 'reported_by', 'client_op_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_issues', function (Blueprint $table) {
            $table->dropUnique(['job_id', 'reported_by', 'client_op_id']);
            $table->dropColumn('client_op_id');
        });

        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->dropColumn('client_op_id');
        });
    }
};
