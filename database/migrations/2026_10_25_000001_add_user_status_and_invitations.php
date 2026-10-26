<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Users can be switched off without deleting them, and new users can be invited by email.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('email');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('deactivated_at');
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('name')->nullable();
            $table->json('role_ids')->nullable();
            $table->json('event_ids')->nullable();
            $table->foreignId('fleet_provider_id')->nullable()->constrained('fleet_providers')->nullOnDelete();
            // sha256 of the short link code; the code itself is only in the email.
            $table->string('code_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedSmallInteger('sent_count')->default(1);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'deactivated_at', 'last_login_at']);
        });
    }
};
