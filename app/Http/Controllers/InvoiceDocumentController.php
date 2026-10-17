<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BrandingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * A printable invoice page — "Print / Save as PDF" in the browser gives
 * the PDF. Open to the buyer it was issued to, the members of the
 * workspace that issued it, and platform admins; anyone else gets 404.
 */
class InvoiceDocumentController extends Controller
{
    public function __invoke(Request $request, int $invoice, BrandingService $branding): View
    {
        // Looked up across tenants on purpose (a buyer may view it from any
        // host); access is decided below, not by the tenant scope.
        $invoice = Invoice::query()->withoutGlobalScope('tenant')->with(['client', 'order', 'tenant'])->findOrFail($invoice);

        abort_unless($this->canView($request->user(), $invoice), 404);

        $order = $invoice->order;
        $currency = $invoice->currency;
        $digits = Money::minorUnits($currency);
        $money = fn (int $minor) => Money::format(Money::fromCents($minor, $digits), $currency, App::getLocale());

        return view('invoices.show', [
            'invoice' => $invoice,
            'money' => $money,
            'brand' => $branding->summary(),
            'seller' => $invoice->tenant?->name,
            'buyer' => [
                'name' => $order?->billing_name ?: $invoice->client?->name,
                'email' => $order?->billing_email ?: $invoice->client?->email,
                'country' => $order?->billing_country,
                'address' => array_filter((array) ($order?->billing_address ?? [])),
            ],
            'locale' => App::getLocale(),
            'direction' => SetLocale::direction(App::getLocale()),
        ]);
    }

    private function canView(User $user, Invoice $invoice): bool
    {
        if ($user->is_admin || ($invoice->client_id !== null && $invoice->client_id === $user->id)) {
            return true;
        }

        return $invoice->tenant instanceof Tenant
            && $invoice->tenant->users()->whereKey($user->id)->exists();
    }
}
