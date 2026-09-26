<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Payments\PaymentGatewayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\UpdatePaymentGatewayRequest;
use App\Models\PaymentGateway;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Workspace → Billing: the workspace's own Stripe gateway (the keys its
 * store checkout charges with). One Stripe gateway per workspace — the
 * same record Admin → Payment Gateways manages — saved through
 * PaymentGatewayService, so keys are encrypted and every change audited.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $service,
    ) {}

    public function update(UpdatePaymentGatewayRequest $request): RedirectResponse
    {
        abort_unless(app(TenantContext::class)->hasTenant(), 404);

        $data = $request->validated();
        $payload = [
            'environment' => $data['environment'],
            'is_active' => $data['is_active'],
            'credentials' => [
                'publishable_key' => $data['publishable_key'] ?? null,
                'secret_key' => $data['secret_key'] ?? null,
            ],
            'webhook_secret' => $data['webhook_secret'] ?? null,
        ];

        $gateway = $this->stripeGateway();

        if ($gateway) {
            $this->service->update($gateway, $payload);
        } else {
            $this->service->create([
                ...$payload,
                'provider' => 'stripe',
                'name' => 'Stripe',
                'display_name' => 'Card (Stripe)',
                'logo' => '💳',
                'is_default' => true,
                'fee_fixed' => 0,
                'fee_percent' => 0,
                'sort_order' => 0,
            ]);
        }

        return back()->with('success', 'Payment gateway saved. Use “Test connection” to check the keys with Stripe.');
    }

    public function test(Request $request): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 404);
        abort_unless($tenant->canManagePayments($request->user()), 403);

        $gateway = $this->stripeGateway();
        if (! $gateway) {
            return back()->with('error', 'Save your Stripe keys first.');
        }

        $result = $this->service->testConnection($gateway);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function stripeGateway(): ?PaymentGateway
    {
        return PaymentGateway::query()->where('provider', 'stripe')->first();
    }
}
