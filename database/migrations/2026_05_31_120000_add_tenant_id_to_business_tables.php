<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scope every multi-tenant business table to a tenant.
 *
 * Columns are nullable here so this migration is safe to apply against
 * existing data; the DatabaseSeeder backfills a default tenant for
 * every legacy row, after which a follow-up migration can promote
 * the column to NOT NULL once the rollout is confirmed.
 *
 * Each table also gets a composite index leading with tenant_id —
 * required for query performance once tenants start to grow.
 */
return new class extends Migration
{
    /**
     * @var list<array{table: string, indexes: list<list<string>>}>
     */
    private array $tables = [
        ['table' => 'categories', 'indexes' => [['tenant_id', 'is_active']]],
        ['table' => 'products',   'indexes' => [['tenant_id', 'status'], ['tenant_id', 'type']]],
        ['table' => 'coupons',    'indexes' => [['tenant_id', 'is_active']]],
        ['table' => 'orders',     'indexes' => [['tenant_id', 'status'], ['tenant_id', 'user_id']]],
        ['table' => 'order_items',    'indexes' => [['tenant_id', 'order_id']]],
        ['table' => 'payments',       'indexes' => [['tenant_id', 'status']]],
        ['table' => 'licenses',       'indexes' => [['tenant_id', 'user_id']]],
        ['table' => 'downloads',      'indexes' => [['tenant_id', 'user_id']]],
        ['table' => 'subscriptions',  'indexes' => [['tenant_id', 'user_id'], ['tenant_id', 'status']]],
        ['table' => 'reviews',        'indexes' => [['tenant_id', 'product_id']]],
        ['table' => 'wishlists',      'indexes' => [['tenant_id', 'user_id']]],
        ['table' => 'blog_posts',     'indexes' => [['tenant_id', 'status']]],
    ];

    public function up(): void
    {
        foreach ($this->tables as $spec) {
            $table = $spec['table'];
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($spec) {
                // nullable + nullOnDelete: legacy rows survive a tenant
                // delete; we won't cascade-delete entire histories on a
                // tenant being wound down. Soft-delete of a tenant should
                // archive the data, not wipe it.
                $t->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->nullOnDelete();

                foreach ($spec['indexes'] as $columns) {
                    $t->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $spec) {
            $table = $spec['table'];
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($spec) {
                foreach ($spec['indexes'] as $columns) {
                    $t->dropIndex(implode('_', array_merge([$spec['table']], $columns, ['index'])));
                }
                $t->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
