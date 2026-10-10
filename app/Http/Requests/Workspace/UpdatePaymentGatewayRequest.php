<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Payments\StripeCredentials;
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
            'publishable_key' => [Rule::requiredIf($isNew), 'nullable', 'string', 'max:255', 'regex:'.StripeCredentials::PUBLISHABLE_KEY_PATTERN],
            'secret_key' => [Rule::requiredIf($isNew), 'nullable', 'string', 'max:255', 'regex:'.StripeCredentials::SECRET_KEY_PATTERN],
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
            'publishable_key.regex' => 'That doesn’t look like a Stripe publishable key (pk_test_… or pk_live_…).',
            'secret_key.regex' => 'That doesn’t look like a Stripe secret or restricted key (sk_test_…, rk_test_…, rkcs_test_… or the live equivalent). Paste it exactly as Stripe shows it.',
            'webhook_secret.starts_with' => 'The webhook signing secret starts with whsec_.',
        ];
    }
}
