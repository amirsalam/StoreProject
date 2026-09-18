<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $slug = (string) $this->input('slug', '');
        $title = (string) $this->input('title', '');

        $this->merge([
            'slug' => $slug !== '' ? Str::slug($slug) : ($title !== '' ? Str::slug($title) : null),
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in([
                Product::TYPE_DIGITAL_DOWNLOAD,
                Product::TYPE_SUBSCRIPTION,
                Product::TYPE_API_ACCESS,
                Product::TYPE_LICENSE,
            ])],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'currency' => ['required', 'string', 'size:3'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'version' => ['nullable', 'string', 'max:50'],
            'license_type' => ['nullable', 'string', 'max:50'],
            'default_activation_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in([
                Product::STATUS_DRAFT,
                Product::STATUS_PUBLISHED,
                Product::STATUS_ARCHIVED,
            ])],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
