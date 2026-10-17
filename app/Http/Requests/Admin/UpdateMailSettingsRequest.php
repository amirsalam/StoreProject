<?php

namespace App\Http\Requests\Admin;

use App\Services\MailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin → Email: the SMTP server used for all outgoing email. A blank
 * password keeps the stored one.
 */
class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind the `admin` middleware
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'host' => ['required_if:enabled,true', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:enabled,true', 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(MailSettings::ENCRYPTIONS)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required_if:enabled,true', 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.required_if' => 'Enter the SMTP server to send through (e.g. smtp.gmail.com).',
            'port.required_if' => 'Enter the SMTP port (usually 587, or 465 for SSL).',
            'from_address.required_if' => 'Enter the address emails are sent from.',
        ];
    }
}
