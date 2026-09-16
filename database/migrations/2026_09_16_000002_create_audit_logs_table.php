<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Names/roles are snapshotted so history survives a user being renamed or deleted.
            $table->string('user_name')->default('System');
            $table->string('user_role')->default('Automation');
            $table->string('action');
            $table->string('target')->nullable();
            $table->text('meta')->nullable();
            $table->nullableMorphs('auditable');
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
