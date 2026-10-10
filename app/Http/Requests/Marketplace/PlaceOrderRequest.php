<?php

namespace App\Http\Requests\Marketplace;

use App\Domain\Marketplace\CheckoutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Checkout requires an authenticated buyer; the route is also
        // behind the auth middleware, this is the belt-and-braces check.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('billing_country'))) {
            $country = strtoupper(trim($this->input('billing_country')));
            $this->merge(['billing_country' => $country !== '' ? $country : null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            // Any ISO 3166-1 code (config/countries.php).
            'billing_country' => ['nullable', 'string', Rule::in(config('countries'))],
            'billing_address' => ['nullable', 'array'],
            'billing_address.line1' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:120'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:32'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'in:'.CheckoutService::METHOD_STRIPE.','.CheckoutService::METHOD_CMI.','.CheckoutService::METHOD_PAYPAL.','.CheckoutService::METHOD_BANK_TRANSFER],
        ];
    }
}
