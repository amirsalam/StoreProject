<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A homepage FAQ entry (Admin → FAQ). `question` and `answer` hold one
 * text per locale; English is required and is the fallback.
 *
 * @property array<string, string|null> $question
 * @property array<string, string|null> $answer
 */
class Faq extends Model
{
    public const FALLBACK_LOCALE = 'en';

    protected $fillable = [
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'question' => 'array',
            'answer' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Shown on the homepage, in order. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Question and answer in the given locale, English where it's missing.
     *
     * @return array{id: int, q: string, a: string}
     */
    public function localized(string $locale): array
    {
        return [
            'id' => $this->id,
            'q' => $this->textFor($this->question, $locale),
            'a' => $this->textFor($this->answer, $locale),
        ];
    }

    /** @param  array<string, string|null>|null  $texts */
    private function textFor(?array $texts, string $locale): string
    {
        $value = trim((string) ($texts[$locale] ?? ''));

        return $value !== '' ? $value : trim((string) ($texts[self::FALLBACK_LOCALE] ?? ''));
    }
}
