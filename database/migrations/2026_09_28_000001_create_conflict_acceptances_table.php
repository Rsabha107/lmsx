<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // The detector's deterministic id, e.g. "SPL-12-34".
            $table->string('conflict_id');
            $table->text('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'conflict_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_acceptances');
    }
};
