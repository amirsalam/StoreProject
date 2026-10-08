<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily rollup table.
 *
 * Dashboards read from here, NOT from the OLTP tables. A 90-day chart
 * is 90 rows, not a 9M-order full-table scan.
 *
 * UNIQUE(tenant_id, metric_key, dimension_key, recorded_on) makes the
 * aggregator idempotent — re-running it for the same day overwrites
 * the existing rows via INSERT … ON CONFLICT (upsert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_key', 64);                        // e.g. revenue_cents, orders_count, new_customers
            $table->string('dimension_key', 64)->default('total');   // 'total' or 'category:42', 'product:7'
            $table->bigInteger('value')->default(0);
            $table->char('currency', 3)->nullable();
            $table->date('recorded_on');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'metric_key', 'dimension_key', 'recorded_on'],
                'daily_metrics_unique',
            );
            $table->index(['metric_key', 'recorded_on']);
            $table->index(['tenant_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
    }
};
