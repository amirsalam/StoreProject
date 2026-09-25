<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace invoices — what a tenant bills their own clients for.
 *
 * Distinct from `payments` (which records gateway charges on marketplace
 * orders) and from `tenant_subscriptions` (the tenant's own SaaS bill).
 * This is the deliverable invoice the tenant sends to a customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 32);                            // INV-2026-0001 (unique per tenant)
            $table->string('status', 16)->default('draft');         // draft|sent|paid|overdue|void
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->string('currency', 3)->default('USD');
            $table->json('line_items')->nullable();                 // [{description, qty, unit_cents, total_cents}]
            $table->text('notes')->nullable();
            $table->date('issued_on');
            $table->date('due_on');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Invoice number unique within tenant (Acme INV-001 ≠ Globex INV-001).
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'due_on']);                 // overdue sweep
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
