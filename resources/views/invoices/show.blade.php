@php
    $statusLabels = [
        'draft' => __('Draft'),
        'sent' => __('Sent'),
        'paid' => __('Paid'),
        'overdue' => __('Overdue'),
        'void' => __('Void'),
    ];
    $licenseLabels = ['regular' => __('Regular License'), 'extended' => __('Extended License')];
    $countryName = $buyer['country'] && class_exists(\Locale::class)
        ? (\Locale::getDisplayRegion('-'.$buyer['country'], $locale) ?: $buyer['country'])
        : $buyer['country'];
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale($locale)->isoFormat('LL') : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Invoice') }} {{ $invoice->number }} — {{ $brand['title'] }}</title>
    @if ($brand['logo_url'])
        <link rel="icon" href="{{ $brand['logo_url'] }}">
    @endif
    <style>
        :root { --ink: #111827; --muted: #6b7280; --line: #e5e7eb; --accent: #4f46e5; --paid: #047857; --void: #b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: var(--ink); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif; }
        .toolbar { max-width: 820px; margin: 24px auto 0; padding: 0 16px; display: flex; gap: 8px; justify-content: flex-end; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--ink); font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .sheet { max-width: 820px; margin: 16px auto 40px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 48px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 48px; height: 48px; object-fit: contain; border-radius: 8px; }
        .brand-name { font-size: 18px; font-weight: 700; }
        .seller { color: var(--muted); font-size: 13px; }
        h1 { margin: 0; font-size: 28px; letter-spacing: -.02em; text-align: end; }
        .number { color: var(--muted); text-align: end; font-family: ui-monospace, Menlo, Consolas, monospace; }
        .badge { display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; border: 1.5px solid currentColor; }
        .badge.paid { color: var(--paid); } .badge.void { color: var(--void); } .badge.other { color: var(--muted); }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 36px 0 28px; }
        .label { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px; }
        .dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 16px; }
        .dl dt { color: var(--muted); } .dl dd { margin: 0; text-align: end; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: start; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 600; padding: 10px 8px; border-bottom: 2px solid var(--ink); }
        td { padding: 12px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .sub { color: var(--muted); font-size: 12px; }
        .totals { margin-top: 16px; margin-inline-start: auto; width: min(320px, 100%); }
        .totals div { display: flex; justify-content: space-between; padding: 6px 8px; }
        .totals .grand { border-top: 2px solid var(--ink); margin-top: 6px; padding-top: 10px; font-size: 18px; font-weight: 700; }
        .foot { margin-top: 40px; color: var(--muted); font-size: 12px; border-top: 1px solid var(--line); padding-top: 16px; }
        @media (max-width: 640px) { .sheet { padding: 24px; border-radius: 0; } .meta { grid-template-columns: 1fr; } h1, .number { text-align: start; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; border-radius: 0; }
            @page { size: A4; margin: 16mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn btn-primary" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <main class="sheet">
        <div class="head">
            <div>
                <div class="brand">
                    @if ($brand['logo_url'])
                        <img src="{{ $brand['logo_url'] }}" alt="">
                    @endif
                    <div class="brand-name">{{ $brand['title'] }}</div>
                </div>
                @if ($seller && $seller !== $brand['title'])
                    <div class="seller">{{ $seller }}</div>
                @endif
            </div>
            <div>
                <h1>{{ __('Invoice') }}</h1>
                <div class="number">{{ $invoice->number }}</div>
                <div style="text-align: end">
                    <span class="badge {{ in_array($invoice->status, ['paid', 'void'], true) ? $invoice->status : 'other' }}">
                        {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                    </span>
                </div>
            </div>
        </div>

        <div class="meta">
            <div>
                <div class="label">{{ __('Billed to') }}</div>
                @if ($buyer['name'])<div><strong>{{ $buyer['name'] }}</strong></div>@endif
                @if ($buyer['email'])<div>{{ $buyer['email'] }}</div>@endif
                @foreach ($buyer['address'] as $line)<div>{{ $line }}</div>@endforeach
                @if ($countryName)<div>{{ $countryName }}</div>@endif
            </div>
            <dl class="dl">
                <dt>{{ __('Issue date') }}</dt><dd>{{ $date($invoice->issued_on) }}</dd>
                @if ($invoice->paid_at)
                    <dt>{{ __('Paid on') }}</dt><dd>{{ $date($invoice->paid_at) }}</dd>
                @elseif ($invoice->due_on)
                    <dt>{{ __('Due date') }}</dt><dd>{{ $date($invoice->due_on) }}</dd>
                @endif
                @if ($invoice->order)
                    <dt>{{ __('Order') }}</dt><dd>{{ $invoice->order->order_number }}</dd>
                    @if ($invoice->order->payment_method)
                        <dt>{{ __('Payment method') }}</dt><dd>{{ ucfirst($invoice->order->payment_method) }}</dd>
                    @endif
                @endif
                <dt>{{ __('Currency') }}</dt><dd>{{ $invoice->currency }}</dd>
            </dl>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('Description') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    <th class="num">{{ __('Unit price') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ((array) $invoice->line_items as $line)
                    <tr>
                        <td>
                            {{ $line['description'] ?? '' }}
                            @if (! empty($line['license']) && isset($licenseLabels[$line['license']]))
                                <div class="sub">{{ $licenseLabels[$line['license']] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $line['qty'] ?? 1 }}</td>
                        <td class="num">{{ $money((int) ($line['unit_cents'] ?? 0)) }}</td>
                        <td class="num">{{ $money((int) ($line['total_cents'] ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="sub">{{ $invoice->notes }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div><span>{{ __('Subtotal') }}</span><span class="num">{{ $money($invoice->subtotal_cents) }}</span></div>
            @if ($invoice->discount_cents > 0)
                <div><span>{{ __('Discount') }}</span><span class="num">− {{ $money($invoice->discount_cents) }}</span></div>
            @endif
            @if ($invoice->tax_cents > 0)
                <div><span>{{ __('Tax') }}</span><span class="num">{{ $money($invoice->tax_cents) }}</span></div>
            @endif
            <div class="grand"><span>{{ __('Total') }}</span><span class="num">{{ $money($invoice->total_cents) }}</span></div>
        </div>

        <div class="foot">
            @if ($invoice->status === 'paid')
                {{ __('Paid in full — thank you for your purchase.') }}
            @elseif ($invoice->status === 'void')
                {{ $invoice->order ? __('This invoice has been voided (the order was refunded).') : __('This invoice has been voided.') }}
            @endif
            @if ($invoice->notes && ! $invoice->order)
                <div style="margin-top: 8px; white-space: pre-line">{{ $invoice->notes }}</div>
            @endif
        </div>
    </main>
</body>
</html>
