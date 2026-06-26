<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Checkout requires an authenticated buyer; the route is also
        // behind the auth middleware, this is the belt-and-braces check.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.line1' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:120'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:32'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
