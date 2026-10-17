<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketplace\CheckoutService;
use App\Domain\Payments\BankTransfer\BankTransferGateway;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Orders: every order with its payment, straight from the orders
 * table (live — unlike the dashboard KPIs, which read the daily rollup).
 * Tenant-scoped by BelongsToTenant like the rest of the admin area.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
        ];

        $query = Order::query()
            ->with([
                'items:id,order_id,product_title,quantity',
                'payments' => fn ($q) => $q->latest('id'),
            ]);

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('order_number', 'like', $term)
                ->orWhere('billing_email', 'like', $term)
                ->orWhere('billing_name', 'like', $term));
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $orders = $query->latest('id')->paginate(20)->withQueryString()->through(fn (Order $order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->billing_name,
            'customer_email' => $order->billing_email,
            'items' => $order->items->map(fn ($item) => ['title' => $item->product_title, 'quantity' => $item->quantity])->values(),
            'total' => (string) $order->total,
            'currency' => $order->currency,
            'status' => $order->status,
            'payment' => $this->paymentSummary($order->payments->first()),
            // Waiting for a bank transfer: the admin confirms it arrived.
            'awaiting_transfer' => $order->status === Order::STATUS_PENDING
                && $order->payment_method === CheckoutService::METHOD_BANK_TRANSFER,
            'created_at' => $order->created_at,
            'paid_at' => $order->paid_at,
        ]);

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => [
                ['value' => Order::STATUS_PENDING, 'label' => __('Pending')],
                ['value' => Order::STATUS_PAID, 'label' => __('Paid')],
                ['value' => Order::STATUS_FAILED, 'label' => __('Failed')],
                ['value' => Order::STATUS_REFUNDED, 'label' => __('Refunded')],
                ['value' => Order::STATUS_CANCELLED, 'label' => __('Cancelled')],
            ],
            // Order confirmations only reach customers once SMTP is set up in
            // Admin → Email; otherwise they are written to the log.
            'mailConfigured' => app(MailSettings::class)->forForm()['enabled'],
            'summary' => [
                'paid_revenue' => (string) Order::query()->where('status', Order::STATUS_PAID)->sum('total'),
                'paid' => Order::query()->where('status', Order::STATUS_PAID)->count(),
                'pending' => Order::query()->where('status', Order::STATUS_PENDING)->count(),
                'refunded' => Order::query()->where('status', Order::STATUS_REFUNDED)->count(),
            ],
        ]);
    }

    /**
     * A bank transfer arrived: mark the order paid — which delivers the
     * products, emails the buyer and issues the invoice, like a card payment.
     */
    public function markPaid(Order $order, BankTransferGateway $bank): RedirectResponse
    {
        if (! $bank->confirm($order)) {
            return back()->with('error', __('Only unpaid bank-transfer orders can be marked as paid here.'));
        }

        ActivityLog::record('order.bank_transfer_confirmed', request()->user(), ['order' => $order->order_number]);

        return back()->with('success', __('Order :number marked as paid. The buyer now has their products.', ['number' => $order->order_number]));
    }

    /**
     * @return array{status: string, gateway: string, reference: ?string}|null
     */
    private function paymentSummary(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'status' => $payment->status,
            'gateway' => $payment->gateway,
            // The Stripe PaymentIntent id (pi_…) — not a secret; lets the
            // admin find the transaction in the Stripe Dashboard.
            'reference' => $payment->gateway_payment_id,
        ];
    }
}
