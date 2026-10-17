<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan catalog — the SaaS pricing tiers offered to tenants.
 *
 * Distinct from `subscriptions` (commit 2026_05_21_120009) which tracks
 * customers buying recurring product subscriptions from the marketplace.
 * `plans` is the platform's own pricing list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();                    // free|pro|business|enterprise
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_cents')->default(0);
            $table->unsignedBigInteger('annual_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('limits');                                  // {users: 5, products: 10, storage_gb: 1, projects: 3}
            $table->json('features');                                // ["api_access", "custom_domains", "sso"]
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);           // assigned on tenant provisioning
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
