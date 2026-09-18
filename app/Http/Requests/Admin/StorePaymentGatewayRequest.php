<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $providers = array_keys((array) config('payment_gateways.providers', []));

        return [
            'provider' => ['required', 'string', Rule::in($providers)],
            'name' => ['required', 'string', 'max:120'],
            'display_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'environment' => ['required', Rule::in([PaymentGateway::ENV_SANDBOX, PaymentGateway::ENV_PRODUCTION])],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:5000'],
            'webhook_secret' => ['nullable', 'string', 'max:5000'],
            'supported_currencies' => ['nullable', 'array'],
            'supported_currencies.*' => ['string', 'size:3'],
            'supported_countries' => ['nullable', 'array'],
            'supported_countries.*' => ['string', 'size:2'],
            'fee_fixed' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'gte:min_amount'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
