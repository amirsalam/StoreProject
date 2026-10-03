<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public-facing identity of a vendor store: company info, logo,
 * banner, contact, social links. Split from `vendors` so the core
 * record stays lean and the profile can grow. One profile per vendor.
 * See docs/marketplace-architecture.md §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();   // {twitter, github, linkedin, ...}
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('country', 2)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};
