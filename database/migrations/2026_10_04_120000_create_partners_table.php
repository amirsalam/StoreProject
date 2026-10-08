<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner / "trusted by" logos on the homepage, managed in Admin →
 * Partners. Starts with the names that were hard-coded on the page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('logo_path')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        DB::table('partners')->insert(collect(['Laravel', 'Stripe', 'Inertia', 'Tailwind', 'Paddle', 'Cloudflare'])
            ->map(fn (string $name, int $i) => [
                'name' => $name,
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
