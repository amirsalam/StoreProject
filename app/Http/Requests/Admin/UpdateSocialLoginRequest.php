<?php

namespace App\Http\Requests\Admin;

use App\Services\SocialLoginSettings;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin → Social login: Google / GitHub OAuth credentials.
 */
class UpdateSocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];
        foreach (SocialLoginSettings::PROVIDERS as $provider) {
            $merged[$provider] = [
                ...(array) $this->input($provider, []),
                'enabled' => $this->boolean("$provider.enabled"),
            ];
        }
        $this->merge($merged);
    }

    public function rules(): array
    {
        $rules = [];
        foreach (SocialLoginSettings::PROVIDERS as $provider) {
            $rules["$provider.enabled"] = ['boolean'];
            $rules["$provider.client_id"] = ['nullable', 'string', 'max:255', 'regex:/^\S*$/'];
            $rules["$provider.client_secret"] = ['nullable', 'string', 'max:255', 'regex:/^\S*$/'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (SocialLoginSettings::PROVIDERS as $provider) {
            $attributes["$provider.client_id"] = __('Client ID');
            $attributes["$provider.client_secret"] = __('Client secret');
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            '*.client_id.regex' => __('The :attribute cannot contain spaces.'),
            '*.client_secret.regex' => __('The :attribute cannot contain spaces.'),
        ];
    }

    /**
     * Switching a provider on needs a client ID and a secret — saved now,
     * saved before, or in .env.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $current = app(SocialLoginSettings::class)->forForm();
                foreach (SocialLoginSettings::PROVIDERS as $provider) {
                    if (! $this->boolean("$provider.enabled") || $current[$provider]['from_env']) {
                        continue;
                    }
                    if (blank($this->input("$provider.client_id"))) {
                        $validator->errors()->add("$provider.client_id", __('Enter the client ID to switch this on.'));
                    }
                    if (blank($this->input("$provider.client_secret")) && ! $current[$provider]['has_secret']) {
                        $validator->errors()->add("$provider.client_secret", __('Enter the client secret to switch this on.'));
                    }
                }
            },
        ];
    }
}
