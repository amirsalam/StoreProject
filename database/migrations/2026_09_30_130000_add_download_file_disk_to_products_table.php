<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which storage holds the product file: the server's private disk
 * (`local`, null for older rows) or cloud storage (`product_cloud`).
 * Kept per product so switching storage later never strands old files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('download_file_disk', 40)->nullable()->after('download_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('download_file_disk');
        });
    }
};
