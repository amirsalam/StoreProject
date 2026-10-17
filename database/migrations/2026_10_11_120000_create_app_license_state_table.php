<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row store for THIS installation's application license (see
     * config/app_license.php). Deliberately no tenant_id: the license
     * covers the whole installation, never one tenant.
     *
     * All timestamps are nullable, so MySQL/MariaDB never silently adds
     * ON UPDATE CURRENT_TIMESTAMP to one of them.
     */
    public function up(): void
    {
        Schema::create('app_license_state', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id');
            $table->string('public_key', 64);
            // Ed25519 secret key, encrypted with APP_KEY (model `encrypted` cast).
            $table->text('secret_key');

            $table->string('activation_id', 26)->nullable();
            $table->string('bound_domain', 253)->nullable();
            $table->text('token')->nullable();
            $table->dateTime('token_expires_at')->nullable();

            $table->dateTime('last_checked_at')->nullable();
            $table->dateTime('last_success_at')->nullable();
            $table->string('last_error_code', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_license_state');
    }
};
