<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage FAQ, managed in Admin → FAQ. Question and answer are stored
 * per locale ({"en": "...", "ar": "...", ...}). Starts with the questions
 * that were in lang/{locale}/messages.php (faq.items).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->json('question');
            $table->json('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $byLocale = [];
        foreach (SetLocale::SUPPORTED as $locale) {
            $items = trans('messages.faq.items', [], $locale);
            $byLocale[$locale] = is_array($items) ? array_values($items) : [];
        }

        $now = now();
        $rows = [];
        foreach ($byLocale['en'] as $i => $item) {
            $question = [];
            $answer = [];
            foreach (SetLocale::SUPPORTED as $locale) {
                $question[$locale] = $byLocale[$locale][$i]['q'] ?? $item['q'];
                $answer[$locale] = $byLocale[$locale][$i]['a'] ?? $item['a'];
            }
            $rows[] = [
                'question' => json_encode($question, JSON_UNESCAPED_UNICODE),
                'answer' => json_encode($answer, JSON_UNESCAPED_UNICODE),
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('faqs')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
