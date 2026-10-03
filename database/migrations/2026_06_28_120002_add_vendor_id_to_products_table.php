<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute products to a vendor. Nullable + nullOnDelete so existing
 * tenant-owned products (no vendor) keep working, and removing a vendor
 * doesn't delete their catalog. See docs/marketplace-architecture.md §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('category_id')
                ->constrained()->nullOnDelete();
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'status']);
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
