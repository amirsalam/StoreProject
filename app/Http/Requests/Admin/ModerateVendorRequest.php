<?php

namespace App\Http\Requests\Admin;

use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by every vendor moderation action. The optional reason is sent
 * with reject/suspend; it is recorded in the audit log and shown to the
 * vendor owner in their notification.
 */
class ModerateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor
            && (bool) $this->user()?->can('moderate', $vendor);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ?string
    {
        $reason = trim((string) $this->validated('reason'));

        return $reason === '' ? null : $reason;
    }
}
