<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending team invitations.
 *
 * One row per outstanding invite. Tokens are random 64-char strings,
 * unique globally so we can route `/invitations/{token}` without
 * exposing the tenant in the URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_id')->constrained('users');
            $table->string('email');
            $table->string('role', 24);                      // owner|admin|member (matches tenant_user.role)
            $table->string('token', 64)->unique();           // shown in the URL, never email-content alone
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One pending invite per (tenant, email) — the action layer
            // enforces this at insert time so we can return a friendly
            // error instead of a SQL violation.
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
