<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Checkpoint templates go to the recycle bin like movement templates (DeletionService).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkpoint_templates', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deletion_id')->nullable()->constrained('deletions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkpoint_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deletion_id');
            $table->dropSoftDeletes();
        });
    }
};
