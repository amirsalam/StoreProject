<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages sent from the public "Contact us" form. Stored rather than
 * only emailed, so the admin inbox works with no mail configuration;
 * an email notification is sent as well when contact.notify_to is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            // nullable + nullOnDelete: consistent with every other business
            // table (see add_tenant_id_to_business_tables). BelongsToTenant
            // auto-fills it on web/api inserts.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            // Set when the sender happened to be logged in; the form does
            // not require an account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('new'); // new|read|archived
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
