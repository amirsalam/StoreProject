<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared input for the public license endpoints (activate / deactivate /
 * validate).
 *
 * `domain` is normalised before validation so the same site always maps
 * to the same activation slot: "https://Example.com/shop" and
 * "example.com" are one domain. It may also be an opaque instance
 * identifier (a UUID, a machine id) for non-web installs.
 *
 * `license_key` is only length-checked, never format-checked: a
 * malformed key must get the same response as an unknown one.
 */
class LicenseActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — the license key itself is the credential.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => self::normalizeDomain($this->input('domain'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'max:64'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9._:-]*[a-z0-9])?$/'],
            'product_id' => ['sometimes', 'integer'],
        ];
    }

    public function licenseKey(): string
    {
        return (string) $this->validated('license_key');
    }

    public function domain(): string
    {
        return (string) $this->validated('domain');
    }

    public function productId(): ?int
    {
        return $this->has('product_id') ? (int) $this->validated('product_id') : null;
    }

    /**
     * Lower-case, then strip any URL scheme, path, query, fragment and
     * trailing dot: "HTTPS://Shop.Example.com./a?b" → "shop.example.com".
     * A port is kept, so "localhost:8080" stays distinct from "localhost".
     */
    public static function normalizeDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $value);
        $value = preg_split('#[/?\#]#', $value, 2)[0];

        return rtrim($value, '.');
    }
}
