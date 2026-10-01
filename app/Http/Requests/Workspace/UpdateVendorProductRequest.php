<?php

namespace App\Http\Requests\Workspace;

use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * A seller's own product form: the admin product rules, open to any user
 * who has opened a store (ownership of the product itself is checked in
 * VendorProductController).
 */
class UpdateVendorProductRequest extends UpdateProductRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->vendor()->exists();
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Subscriptions belong to the platform's billing module.
            'type' => ['required', Rule::in([
                Product::TYPE_DIGITAL_DOWNLOAD,
                Product::TYPE_LICENSE,
                Product::TYPE_API_ACCESS,
            ])],
        ];
    }
}
