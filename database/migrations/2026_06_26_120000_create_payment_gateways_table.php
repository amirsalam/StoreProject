<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateway configurations managed from the admin dashboard.
 *
 * Sensitive fields (`credentials`, `webhook_secret`) are stored as
 * encrypted blobs via the model's `encrypted` casts — hence TEXT columns
 * (ciphertext is much longer than the plaintext). Per-provider credential
 * shapes vary, so they live in the flexible `credentials` JSON map keyed
 * by the field names declared in config/payment_gateways.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('provider', 40);          // registry key: stripe, paypal, cod, ...
            $table->string('name');                  // internal / canonical name
            $table->string('display_name');          // customer-facing label
            $table->text('description')->nullable();
            $table->string('logo')->nullable();

            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('environment', 16)->default('sandbox'); // sandbox | production

            $table->text('credentials')->nullable();      // encrypted:array
            $table->text('webhook_secret')->nullable();   // encrypted

            $table->json('supported_currencies')->nullable();
            $table->json('supported_countries')->nullable();

            $table->decimal('fee_fixed', 12, 2)->default(0);
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('last_connection_at')->nullable();
            $table->string('webhook_status', 24)->default('unknown'); // unknown | healthy | failing

            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
