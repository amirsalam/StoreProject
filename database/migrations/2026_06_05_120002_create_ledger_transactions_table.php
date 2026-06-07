<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of every wallet movement.
 *
 * - amount_cents is always POSITIVE. Direction is in `type`.
 * - balance_after_cents is the wallet balance AFTER this entry. It is
 *   computed under the wallet's SELECT FOR UPDATE so it can never
 *   disagree with reality.
 * - idempotency_key UNIQUE — the *write-time* dedup gate. Callers
 *   construct a deterministic key (e.g. "payment:{$paymentId}:credit")
 *   and rely on the unique violation to refuse a second write.
 *
 * No down-direction operations are ever supported: ledger rows are
 * immutable. A mistake becomes a `reversal` entry, never an update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['credit', 'debit', 'refund', 'adjustment', 'reversal']);
            $table->bigInteger('amount_cents');                     // > 0
            $table->bigInteger('balance_after_cents');
            $table->char('currency', 3);
            $table->string('idempotency_key', 64)->unique();
            $table->string('reference', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
            $table->index('payment_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
