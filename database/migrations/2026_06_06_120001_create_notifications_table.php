<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notification rows. Mirrors the on-screen bell list.
 *
 * Real-time delivery (websockets) and email delivery are separate
 * concerns layered on top — they fire from NotificationService when
 * the row is created. Storage of the row is the source of truth for
 * "have we shown this to the user yet?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);                              // e.g. order.created, vendor.approved
            $table->enum('level', ['info', 'success', 'warning', 'critical'])->default('info');
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('action_url', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Covering index for the unread-feed query
            // "SELECT * WHERE user_id = ? AND read_at IS NULL ORDER BY created_at DESC".
            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
