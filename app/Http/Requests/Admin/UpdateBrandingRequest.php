<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:60'],
            'logo' => [
                'sometimes',
                'nullable',
                'file',
                'max:2048', // 2 MB
                'mimes:png,jpg,jpeg,svg,webp',
                'mimetypes:image/png,image/jpeg,image/svg+xml,image/webp',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.max' => 'The logo must be under 2 MB.',
            'logo.mimes' => 'The logo must be a PNG, JPG, SVG, or WebP file.',
            'logo.mimetypes' => 'The logo must be a PNG, JPG, SVG, or WebP file.',
            'title.max' => 'The site title must be 60 characters or fewer.',
        ];
    }
}
