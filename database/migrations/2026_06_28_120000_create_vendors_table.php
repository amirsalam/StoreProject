<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vendor is a seller hosted *within* a tenant's marketplace. Today
 * "vendor" was conflated with "tenant" (one tenant = one seller); this
 * introduces the entity so a tenant can host many sellers, each with a
 * public storefront. See docs/marketplace-architecture.md §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            // nullable + nullOnDelete: consistent with every other business
            // table (see add_tenant_id_to_business_tables) — archive, don't
            // wipe, when a tenant winds down. BelongsToTenant auto-fills it
            // on web/api inserts.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('pending'); // pending|active|suspended|rejected
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Slug is unique per tenant — the public store URL key.
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
