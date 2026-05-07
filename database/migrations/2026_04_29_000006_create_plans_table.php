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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'PLN-2026-0418'
            $table->string('name'); // e.g., 'Match Day 4 - Active'
            $table->dateTime('date'); // Plan execution date and time
            $table->enum('status', ['draft', 'upcoming', 'active', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('movement_template_id')->nullable()->constrained()->onDelete('set null'); // Optional: based on template
            $table->text('notes')->nullable();
            $table->integer('movements_count')->default(0);
            $table->integer('teams_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
