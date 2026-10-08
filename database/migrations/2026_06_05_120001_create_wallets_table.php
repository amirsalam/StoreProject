<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-(tenant, user, currency) wallet row.
 *
 * - balance_cents is BIGINT to avoid float drift; we never store money
 *   as DECIMAL in the wallet (DECIMAL is fine for orders/payments which
 *   are display-and-archive; the wallet does math).
 * - version is an optimistic-concurrency counter, incremented on every
 *   credit/debit. Even with row locks, this gives the application a
 *   second check for racy code paths during distributed writes.
 * - UNIQUE(tenant_id, user_id, currency) — at most one wallet per
 *   triple. The application uses firstOrCreate() against this UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('balance_cents')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'currency']);
            $table->index(['user_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
