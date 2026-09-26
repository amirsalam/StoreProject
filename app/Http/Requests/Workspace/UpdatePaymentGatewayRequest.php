<?php

namespace App\Http\Requests\Workspace;

use App\Models\PaymentGateway;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The workspace's Stripe gateway, set from Workspace → Billing.
 *
 * Keys are required only when no gateway exists yet; on later saves a
 * blank key or webhook secret keeps the stored value (so the owner can
 * switch it on/off without re-typing secrets).
 */
class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(TenantContext::class)->current();

        return $tenant !== null && $tenant->canManagePayments($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'publishable_key' => is_string($this->input('publishable_key')) ? trim($this->input('publishable_key')) : null,
            'secret_key' => is_string($this->input('secret_key')) ? trim($this->input('secret_key')) : null,
            'webhook_secret' => is_string($this->input('webhook_secret')) ? trim($this->input('webhook_secret')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isNew = ! PaymentGateway::query()->where('provider', 'stripe')->exists();

        return [
            'environment' => ['required', Rule::in([PaymentGateway::ENV_SANDBOX, PaymentGateway::ENV_PRODUCTION])],
            'publishable_key' => [Rule::requiredIf($isNew), 'nullable', 'string', 'max:255', 'starts_with:pk_test_,pk_live_'],
            'secret_key' => [Rule::requiredIf($isNew), 'nullable', 'string', 'max:255', 'starts_with:sk_test_,sk_live_,rk_test_,rk_live_'],
            'webhook_secret' => ['nullable', 'string', 'max:255', 'starts_with:whsec_'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'publishable_key.starts_with' => 'The publishable key starts with pk_test_ or pk_live_.',
            'secret_key.starts_with' => 'The secret key starts with sk_test_ or sk_live_ (or rk_ for a restricted key).',
            'webhook_secret.starts_with' => 'The webhook signing secret starts with whsec_.',
        ];
    }
}
