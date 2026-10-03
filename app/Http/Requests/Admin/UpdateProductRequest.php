<?php

namespace App\Http\Requests\Admin;

use App\Domain\Marketplace\ProductFileService;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'currency' => strtoupper(trim((string) $this->input('currency', ''))),
            'remove_download_file' => $this->boolean('remove_download_file'),
            // Blank = no support included.
            'support_months' => $this->filled('support_months') ? $this->input('support_months') : 0,
            // Screenshots arrive as one URL per line.
            'gallery' => $this->has('screenshots')
                ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->input('screenshots')) ?: [])))
                : $this->input('gallery'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($product->id)],
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
            // Any ISO 4217 currency (config/currencies.php).
            'currency' => ['required', 'string', Rule::in(array_keys(config('currencies')))],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'version' => ['nullable', 'string', 'max:50'],
            'license_type' => ['nullable', 'string', 'max:50'],
            'default_activation_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            // Purchase options shown on the product page.
            'extended_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'support_months' => ['integer', 'min:0', 'max:60'],
            'support_extension_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'live_preview_url' => ['nullable', 'url:http,https', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => ['url:http,https', 'max:2048'],
            'status' => ['required', Rule::in([
                Product::STATUS_DRAFT,
                Product::STATUS_PUBLISHED,
                Product::STATUS_ARCHIVED,
            ])],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            // The file buyers download; stored privately by ProductFileService.
            'download_file' => ['nullable', 'file', 'max:'.intdiv(ProductFileService::maxUploadBytes(), 1024)],
            'remove_download_file' => ['boolean'],
            // Set instead of download_file when the browser uploaded straight to cloud storage.
            'download_file_token' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // PHP rejects an over-size upload before Laravel sees it, which
        // would otherwise read as a vague "failed to upload".
        $max = intdiv(ProductFileService::maxUploadBytes(), 1024 * 1024);

        return [
            'download_file.uploaded' => __('The file is too large. This server accepts files up to :size MB.', ['size' => $max]),
            'download_file.max' => __('The file is too large. This server accepts files up to :size MB.', ['size' => $max]),
        ];
    }
}
