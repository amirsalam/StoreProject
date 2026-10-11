<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActivateAppLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is behind the `admin` middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'max:64'],
        ];
    }
}
