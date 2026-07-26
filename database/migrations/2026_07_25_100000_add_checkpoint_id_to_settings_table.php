<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pma_settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'scope', 'scope_id']);
            $table->unsignedBigInteger('checkpoint_id')->nullable()->after('scope_id');
            $table->foreign('checkpoint_id')->references('id')->on('checkpoints')->nullOnDelete();
            $table->unique(['key', 'scope', 'scope_id', 'checkpoint_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pma_settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'scope', 'scope_id', 'checkpoint_id']);
            $table->dropForeign(['checkpoint_id']);
            $table->dropColumn('checkpoint_id');
            $table->unique(['key', 'scope', 'scope_id']);
        });
    }
};
