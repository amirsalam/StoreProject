<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an idempotency_key to payments so the checkout endpoint can
 * dedupe client retries before they ever hit the gateway.
 *
 * Also tighten gateway_payment_id to UNIQUE (was a plain string before)
 * — this is the constraint that makes "create-or-fetch" race-free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('gateway_payment_id');
            $table->unique('idempotency_key', 'payments_idempotency_key_unique');

            // gateway_payment_id can be null while we await the gateway's
            // ack, but once set it must be globally unique.
            $table->unique('gateway_payment_id', 'payments_gateway_payment_id_unique');

            $table->index(['status', 'created_at'], 'payments_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_idempotency_key_unique');
            $table->dropUnique('payments_gateway_payment_id_unique');
            $table->dropIndex('payments_status_created_idx');
            $table->dropColumn('idempotency_key');
        });
    }
};
