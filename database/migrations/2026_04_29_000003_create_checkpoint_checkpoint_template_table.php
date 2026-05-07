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
        Schema::create('checkpoint_checkpoint_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('checkpoint_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0); // Sequence order
            $table->boolean('is_required')->default(true);
            $table->integer('estimated_minutes')->nullable(); // Time for this specific checkpoint in this template
            $table->timestamps();

            // Ensure unique checkpoint per template and proper ordering
            $table->unique(['checkpoint_template_id', 'checkpoint_id'], 'chkpt_tmpl_chkpt_unique');
            $table->index(['checkpoint_template_id', 'order'], 'chkpt_tmpl_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkpoint_checkpoint_template');
    }
};
