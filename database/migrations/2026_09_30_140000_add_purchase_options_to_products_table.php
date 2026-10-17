<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace-style purchase options on the product page:
 *  - an Extended License at its own price (next to the Regular License);
 *  - item support included for N months, extendable to 12 at checkout;
 *  - a Live Preview link (screenshots use the existing `gallery` column).
 * Licenses remember which tier was bought.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('extended_price', 10, 2)->nullable()->after('sale_price');
            $table->unsignedSmallInteger('support_months')->default(6)->after('download_limit');
            $table->decimal('support_extension_price', 10, 2)->nullable()->after('support_months');
            $table->string('live_preview_url', 2048)->nullable()->after('thumbnail');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->string('tier', 20)->default('regular')->after('license_key');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['extended_price', 'support_months', 'support_extension_price', 'live_preview_url']);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
