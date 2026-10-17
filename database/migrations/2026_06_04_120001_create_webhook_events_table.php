<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only webhook log used as an idempotency gate.
 *
 * Pattern from the payment-architecture design: the very first thing
 * a webhook handler does is INSERT a row with the gateway's event id.
 * The UNIQUE constraint catches duplicate deliveries — the inserting
 * worker is the one that gets to process; everyone else returns 200
 * to the gateway and exits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 24);                          // stripe|paddle (forward-looking)
            $table->string('gateway_event_id')->unique();           // evt_xxx — UNIQUE is the dedup gate
            $table->string('event_type', 80);                       // customer.subscription.updated, etc.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->index(['gateway', 'event_type']);
            $table->index(['tenant_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
