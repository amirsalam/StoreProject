<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store sales get an invoice too: every paid order produces one (see
 * OrderInvoiceService). `order_id` links it — unique, so a replayed
 * payment event never issues a second invoice — and `discount_cents`
 * carries the coupon so subtotal − discount + tax = total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('discount_cents')->default(0)->after('subtotal_cents');

            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('discount_cents');
        });
    }
};
