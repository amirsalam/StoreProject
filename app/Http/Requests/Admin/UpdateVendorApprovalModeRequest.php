<?php

namespace App\Http\Requests\Admin;

use App\Domain\Marketplace\VendorApprovalMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorApprovalModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(VendorApprovalMode::class)],
        ];
    }

    public function mode(): VendorApprovalMode
    {
        return VendorApprovalMode::from($this->validated('mode'));
    }
}
