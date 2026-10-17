<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The deliverable file itself lives on the private disk at
 * products.download_file_path; these keep the name the buyer downloads it
 * as and its size, so the path never has to leave the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('download_file_name')->nullable()->after('download_file_path');
            $table->unsignedBigInteger('download_file_size')->nullable()->after('download_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['download_file_name', 'download_file_size']);
        });
    }
};
