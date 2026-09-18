<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'status' => (string) $request->string('status'),
            'search' => (string) $request->string('search'),
        ];

        $query = Invoice::query()->with('client:id,name,email');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('number', 'like', $term)
                ->orWhereHas('client', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
            );
        }

        return Inertia::render('workspace/invoices/index', [
            'invoices' => $query->latest('issued_on')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'subtotal_cents' => ['required', 'integer', 'min:0'],
            'tax_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'line_items' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subtotal = (int) $data['subtotal_cents'];
        $tax = (int) ($data['tax_cents'] ?? 0);

        $invoice = Invoice::create($data + [
            'number' => $this->nextInvoiceNumber(),
            'status' => Invoice::STATUS_DRAFT,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
        ]);

        return redirect()
            ->route('workspace.invoices.index')
            ->with('success', "Invoice {$invoice->number} created.");
    }

    public function markSent(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            $invoice->update([
                'status' => Invoice::STATUS_SENT,
                'sent_at' => now(),
            ]);
        }

        return redirect()->route('workspace.invoices.index');
    }

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        if (in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE], true)) {
            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
            ]);
        }

        return redirect()->route('workspace.invoices.index');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()
            ->route('workspace.invoices.index')
            ->with('success', 'Invoice removed.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Invoice::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => Invoice::STATUS_SENT, 'label' => 'Sent'],
            ['value' => Invoice::STATUS_PAID, 'label' => 'Paid'],
            ['value' => Invoice::STATUS_OVERDUE, 'label' => 'Overdue'],
            ['value' => Invoice::STATUS_VOID, 'label' => 'Void'],
        ];
    }

    /**
     * Allocate the next invoice number for the current tenant.
     * Format: INV-YYYY-####, sequential within tenant.
     */
    private function nextInvoiceNumber(): string
    {
        $tenant = app(TenantContext::class)->current();
        $year = now()->year;

        $count = Invoice::query()
            ->where('number', 'like', "INV-{$year}-%")
            ->count();

        return sprintf('INV-%d-%04d', $year, $count + 1);
    }
}
