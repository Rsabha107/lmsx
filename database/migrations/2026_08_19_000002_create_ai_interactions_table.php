<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('event_id')->nullable()->constrained();
            $table->text('question');
            // Tool name + scoping params only — never the tool's output payload.
            $table->json('tool_calls')->nullable();
            $table->text('response_summary')->nullable();
            $table->enum('status', ['success', 'failed', 'timeout', 'rate_limited']);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
