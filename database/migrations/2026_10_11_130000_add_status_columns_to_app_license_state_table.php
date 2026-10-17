<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the license server last reported, for the Installation license
     * page: the license status and whether the domain counts as production
     * or development.
     */
    public function up(): void
    {
        Schema::table('app_license_state', function (Blueprint $table) {
            $table->string('license_status', 16)->nullable()->after('bound_domain');
            $table->string('environment', 16)->nullable()->after('license_status');
        });
    }

    public function down(): void
    {
        Schema::table('app_license_state', function (Blueprint $table) {
            $table->dropColumn(['license_status', 'environment']);
        });
    }
};
