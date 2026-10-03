<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_id');
            $table->string('provider_email')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // One row per provider account globally — no two users can be
            // bound to the same external identity.
            $table->unique(['provider', 'provider_id']);
            // Cap rows per user per provider at one (a user can have a
            // Google AND a GitHub link, but not two Googles).
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
