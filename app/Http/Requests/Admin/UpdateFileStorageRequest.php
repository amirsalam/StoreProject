<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use App\Services\FileStorageSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFileStorageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'path_style' => $this->boolean('path_style'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasSecret = filled(Setting::get('storage.secret'));
        $needsEndpoint = in_array($this->input('provider'), ['r2', 'custom'], true);

        return [
            'enabled' => ['boolean'],
            'provider' => ['required', Rule::in(array_keys(FileStorageSettings::PROVIDERS))],
            'bucket' => ['required_if:enabled,true', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9.\-_]{1,253}$/'],
            'region' => ['nullable', 'string', 'max:64'],
            'endpoint' => [$needsEndpoint ? 'required_if:enabled,true' : 'nullable', 'nullable', 'url:https,http', 'max:255'],
            'key' => ['required_if:enabled,true', 'nullable', 'string', 'max:255'],
            // Blank keeps the stored secret; needed the first time.
            'secret' => [$hasSecret ? 'nullable' : 'required_if:enabled,true', 'nullable', 'string', 'max:255'],
            'path_style' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bucket.regex' => __('Use the bucket name only (lowercase letters, numbers, dots and dashes) — not a URL.'),
        ];
    }
}
