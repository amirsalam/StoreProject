<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Extended License's own file: buyers of the Extended License download
 * this one when it's set (the product file otherwise). Same shape as the
 * product file: where it is (disk + path) and what the buyer sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('extended_file_path')->nullable()->after('download_file_size');
            $table->string('extended_file_disk', 40)->nullable()->after('extended_file_path');
            $table->string('extended_file_name')->nullable()->after('extended_file_disk');
            $table->unsignedBigInteger('extended_file_size')->nullable()->after('extended_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['extended_file_path', 'extended_file_disk', 'extended_file_name', 'extended_file_size']);
        });
    }
};
