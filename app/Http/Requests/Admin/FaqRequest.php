<?php

namespace App\Http\Requests\Admin;

use App\Http\Middleware\SetLocale;
use App\Models\Faq;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create / update a homepage FAQ entry (Admin → FAQ). English is
 * required; the other languages fall back to it when left empty.
 */
class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'question' => ['required', 'array'],
            'answer' => ['required', 'array'],
            'is_active' => ['boolean'],
        ];

        foreach (SetLocale::SUPPORTED as $locale) {
            $required = $locale === Faq::FALLBACK_LOCALE ? 'required' : 'nullable';
            $rules["question.$locale"] = [$required, 'string', 'max:255'];
            $rules["answer.$locale"] = [$required, 'string', 'max:5000'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (SetLocale::SUPPORTED as $locale) {
            $attributes["question.$locale"] = __('question');
            $attributes["answer.$locale"] = __('answer');
        }

        return $attributes;
    }

    /**
     * Only the supported locales, trimmed.
     *
     * @return array{question: array<string, string>, answer: array<string, string>, is_active: bool}
     */
    public function faqData(): array
    {
        $pick = fn (string $field) => collect(SetLocale::SUPPORTED)
            ->mapWithKeys(fn (string $locale) => [$locale => trim((string) $this->input("$field.$locale", ''))])
            ->all();

        return [
            'question' => $pick('question'),
            'answer' => $pick('answer'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
