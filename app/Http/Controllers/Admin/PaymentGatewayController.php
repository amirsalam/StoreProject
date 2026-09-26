<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\OrderPaymentProcessor;
use App\Domain\Payments\PaymentGatewayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentGatewayRequest;
use App\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $service) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),     // active | inactive
            'environment' => (string) $request->string('environment'),
        ];

        $query = PaymentGateway::query()->ordered();

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('display_name', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('provider', 'like', $term));
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        if ($filters['environment'] !== '') {
            $query->where('environment', $filters['environment']);
        }

        return Inertia::render('admin/payment-gateways/index', [
            'gateways' => $query->paginate(20)->withQueryString()->through(fn (PaymentGateway $g) => $this->summary($g)),
            'filters' => $filters,
            'providers' => $this->providerLabels(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/payment-gateways/create', [
            'providers' => $this->providers(),
            'stripeWebhook' => $this->stripeWebhook(),
        ]);
    }

    public function store(StorePaymentGatewayRequest $request): RedirectResponse
    {
        $gateway = $this->service->create($request->validated());

        return redirect()
            ->route('admin.payment-gateways.index')
            ->with('success', "Gateway \"{$gateway->display_name}\" created.");
    }

    public function edit(PaymentGateway $paymentGateway): Response
    {
        return Inertia::render('admin/payment-gateways/edit', [
            'gateway' => $this->editPayload($paymentGateway),
            'providers' => $this->providers(),
            'stripeWebhook' => $this->stripeWebhook(),
        ]);
    }

    public function update(UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $this->service->update($paymentGateway, $request->validated());

        return redirect()
            ->route('admin.payment-gateways.index')
            ->with('success', "Gateway \"{$paymentGateway->display_name}\" updated.");
    }

    public function destroy(PaymentGateway $paymentGateway): RedirectResponse
    {
        $name = $paymentGateway->display_name;
        $this->service->delete($paymentGateway);

        return redirect()
            ->route('admin.payment-gateways.index')
            ->with('success', "Gateway \"{$name}\" deleted.");
    }

    public function toggle(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $this->service->setActive($paymentGateway, ! $paymentGateway->is_active);

        return back()->with('success', 'Gateway status updated.');
    }

    public function setDefault(PaymentGateway $paymentGateway): RedirectResponse
    {
        $this->service->setDefault($paymentGateway);

        return back()->with('success', "\"{$paymentGateway->display_name}\" is now the default gateway.");
    }

    public function test(PaymentGateway $paymentGateway): RedirectResponse
    {
        $result = $this->service->testConnection($paymentGateway);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->service->reorder($data['ids']);

        return back()->with('success', 'Order updated.');
    }

    /**
     * The list-row shape — NO secret values, only whether each credential
     * is configured.
     *
     * @return array<string, mixed>
     */
    private function summary(PaymentGateway $g): array
    {
        return [
            'id' => $g->id,
            'provider' => $g->provider,
            'provider_label' => config("payment_gateways.providers.{$g->provider}.label", $g->provider),
            'name' => $g->name,
            'display_name' => $g->display_name,
            'logo' => $g->logo ?? config("payment_gateways.providers.{$g->provider}.logo"),
            'is_active' => $g->is_active,
            'is_default' => $g->is_default,
            'environment' => $g->environment,
            'fee_fixed' => $g->fee_fixed,
            'fee_percent' => $g->fee_percent,
            'webhook_status' => $g->webhook_status,
            'has_webhook_secret' => filled($g->getRawOriginal('webhook_secret')),
            'last_connection_at' => $g->last_connection_at,
            'sort_order' => $g->sort_order,
        ];
    }

    /**
     * The edit-form shape — every non-secret field plus per-credential
     * "is configured" booleans. Secret values are never sent.
     *
     * @return array<string, mixed>
     */
    /**
     * What to register in the Stripe Dashboard: this store's webhook URL
     * (built from the current host, so it resolves to this store) and the
     * event types checkout handles.
     *
     * @return array{url: string, events: list<string>}
     */
    private function stripeWebhook(): array
    {
        return [
            'url' => route('webhooks.stripe'),
            'events' => OrderPaymentProcessor::HANDLED_EVENTS,
        ];
    }

    private function editPayload(PaymentGateway $g): array
    {
        return [
            'id' => $g->id,
            'provider' => $g->provider,
            'name' => $g->name,
            'display_name' => $g->display_name,
            'description' => $g->description,
            'logo' => $g->logo,
            'is_active' => $g->is_active,
            'is_default' => $g->is_default,
            'environment' => $g->environment,
            'credential_status' => $g->credentialStatus(),
            'has_webhook_secret' => filled($g->getRawOriginal('webhook_secret')),
            'supported_currencies' => $g->supported_currencies ?? [],
            'supported_countries' => $g->supported_countries ?? [],
            'fee_fixed' => $g->fee_fixed,
            'fee_percent' => $g->fee_percent,
            'min_amount' => $g->min_amount,
            'max_amount' => $g->max_amount,
            'sort_order' => $g->sort_order,
        ];
    }

    /**
     * The full provider catalog (labels + credential field schema) the
     * form needs to render dynamic inputs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function providers(): array
    {
        $out = [];
        foreach ((array) config('payment_gateways.providers', []) as $key => $meta) {
            $fields = [];
            foreach (($meta['fields'] ?? []) as $fieldKey => $field) {
                $fields[] = [
                    'key' => $fieldKey,
                    'label' => $field['label'] ?? $fieldKey,
                    'secret' => (bool) ($field['secret'] ?? false),
                    'required' => (bool) ($field['required'] ?? false),
                ];
            }

            $out[] = [
                'value' => $key,
                'label' => $meta['label'] ?? $key,
                'logo' => $meta['logo'] ?? null,
                'supports_webhook' => (bool) ($meta['supports_webhook'] ?? false),
                'fields' => $fields,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function providerLabels(): array
    {
        $out = [];
        foreach ((array) config('payment_gateways.providers', []) as $key => $meta) {
            $out[] = ['value' => $key, 'label' => $meta['label'] ?? $key];
        }

        return $out;
    }
}
