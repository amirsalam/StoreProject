import PaginationLinks from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Store } from 'lucide-react';

interface Sale {
    id: number;
    order_number: string;
    product: string;
    quantity: number;
    total: string;
    currency: string;
    customer: string | null;
    date: string | null;
}

interface Props {
    vendor: { id: number; name: string; slug: string } | null;
    sales: Paginated<Sale> | null;
    summary: { revenue: string; orders: number; units: number };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/products' },
    { title: 'Sales', href: '/workspace/sales' },
];

function money(amount: string, currency = 'USD'): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(amount));
}

export default function SellerSales({ vendor, sales, summary }: Props) {
    const { __ } = useTranslate();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Sales')} />

            <div className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{__('Sales')}</h1>
                    <p className="text-sm text-muted-foreground">{__('Paid orders for the products in your store.')}</p>
                </div>

                {!vendor || !sales ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card p-10 text-center">
                        <Store className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">{__('Open your store to start selling your own digital products.')}</p>
                        <Button asChild>
                            <Link href={route('workspace.vendor.edit')}>{__('Open your store')}</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <SummaryCard label={__('Revenue')} value={money(summary.revenue)} />
                            <SummaryCard label={__('Paid orders')} value={String(summary.orders)} />
                            <SummaryCard label={__('Units sold')} value={String(summary.units)} />
                        </div>

                        {sales.data.length === 0 ? (
                            <div className="rounded-lg border border-dashed bg-card p-10 text-center text-sm text-muted-foreground">
                                {__('No sales yet. Once a buyer pays for one of your products it appears here.')}
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border bg-card">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-xs tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3 text-start font-medium">{__('Order')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Product')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Customer')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Total')}</th>
                                            <th className="px-4 py-3 text-start font-medium">{__('Date')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {sales.data.map((sale) => (
                                            <tr key={sale.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                                    {sale.order_number}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {sale.product}
                                                    {sale.quantity > 1 && <span className="text-muted-foreground"> ×{sale.quantity}</span>}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">{sale.customer ?? '—'}</td>
                                                <td className="px-4 py-3 font-medium tabular-nums">{money(sale.total, sale.currency)}</td>
                                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                                    {sale.date ? new Date(sale.date).toLocaleDateString() : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {sales.last_page > 1 && <PaginationLinks links={sales.links} />}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function SummaryCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="font-mono text-[11px] tracking-wider text-muted-foreground uppercase">{label}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}
