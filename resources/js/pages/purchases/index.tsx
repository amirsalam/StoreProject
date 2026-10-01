import PaginationLinks from '@/components/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/pages/admin/products/product-form';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy, Download, KeyRound, ShoppingBag } from 'lucide-react';
import { useState } from 'react';

interface Purchase {
    id: number;
    title: string;
    type: string;
    product_url: string | null;
    thumbnail: string | null;
    version: string | null;
    order_number: string;
    purchased_at: string | null;
    quantity: number;
    support_until: string | null;
    licenses: {
        key: string;
        tier: 'regular' | 'extended';
        status: 'active' | 'expired' | 'revoked';
        activation_limit: number;
        activations_used: number;
        expires_at: string | null;
    }[];
    download: {
        url: string;
        file_name: string | null;
        file_size: number | null;
        count: number;
        max: number | null;
        available: boolean;
        reason: string | null;
    } | null;
}

interface PurchasesProps {
    purchases: Paginated<Purchase>;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My purchases', href: '/purchases' }];

export default function PurchasesIndex({ purchases }: PurchasesProps) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('My purchases')} />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.error && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-2 text-sm text-destructive">{flash.error}</div>
                )}

                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{__('My purchases')}</h1>
                    <p className="text-sm text-muted-foreground">{__('Download your files and copy your license keys to activate your products.')}</p>
                </div>

                {purchases.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card p-10 text-center">
                        <ShoppingBag className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">{__('You haven’t bought anything yet.')}</p>
                        <Button asChild>
                            <Link href={route('products.index')}>{__('Browse products')}</Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {purchases.data.map((item) => (
                            <PurchaseCard key={item.id} item={item} />
                        ))}
                    </div>
                )}

                {purchases.last_page > 1 && <PaginationLinks links={purchases.links} />}
            </div>
        </AppLayout>
    );
}

function PurchaseCard({ item }: { item: Purchase }) {
    const { __ } = useTranslate();

    return (
        <article className="rounded-lg border bg-card p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="truncate text-base font-semibold">
                        {item.product_url ? (
                            <Link href={item.product_url} className="hover:underline">
                                {item.title}
                            </Link>
                        ) : (
                            item.title
                        )}
                    </h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        <span dir="ltr" className="font-mono">
                            {item.order_number}
                        </span>
                        {item.purchased_at && <> · {new Date(item.purchased_at).toLocaleDateString()}</>}
                        {item.version && <> · {__('Version :version', { version: item.version })}</>}
                        {item.quantity > 1 && <> · ×{item.quantity}</>}
                    </p>
                    {item.support_until && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {new Date(item.support_until) > new Date()
                                ? __('Support until :date', { date: new Date(item.support_until).toLocaleDateString() })
                                : __('Support ended on :date', { date: new Date(item.support_until).toLocaleDateString() })}
                        </p>
                    )}
                </div>
                <Badge variant="secondary">{__(item.type.replace(/_/g, ' '))}</Badge>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-2">
                {item.download && <DownloadBox download={item.download} />}
                {item.licenses.map((license) => (
                    <LicenseBox key={license.key} license={license} />
                ))}
                {!item.download && item.licenses.length === 0 && (
                    <p className="text-sm text-muted-foreground">{__('Nothing to download for this item.')}</p>
                )}
            </div>
        </article>
    );
}

function DownloadBox({ download }: { download: NonNullable<Purchase['download']> }) {
    const { __ } = useTranslate();
    const remaining = download.max !== null ? Math.max(0, download.max - download.count) : null;

    return (
        <div className="space-y-2 rounded-md border bg-muted/20 p-4">
            <p className="flex items-center gap-1.5 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                <Download className="size-3.5" /> {__('Download')}
            </p>
            {download.file_name && (
                <p className="truncate text-sm" dir="ltr">
                    {download.file_name} <span className="text-xs text-muted-foreground">{formatBytes(download.file_size)}</span>
                </p>
            )}
            <p className="text-xs text-muted-foreground">
                {remaining === null
                    ? __('Unlimited downloads')
                    : __(':remaining of :max downloads left', { remaining, max: download.max ?? 0 })}
            </p>
            {download.available ? (
                // A plain link: the response is a file, not an Inertia page.
                <Button asChild size="sm">
                    <a href={download.url}>
                        <Download /> {__('Download')}
                    </a>
                </Button>
            ) : (
                <p className="text-xs text-amber-700 dark:text-amber-400">{download.reason}</p>
            )}
        </div>
    );
}

function LicenseBox({ license }: { license: Purchase['licenses'][number] }) {
    const { __ } = useTranslate();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(license.key);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="space-y-2 rounded-md border bg-muted/20 p-4">
            <div className="flex items-center justify-between gap-2">
                <p className="flex items-center gap-1.5 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    <KeyRound className="size-3.5" /> {license.tier === 'extended' ? __('Extended License') : __('Regular License')}
                </p>
                <Badge variant={license.status === 'active' ? 'default' : 'destructive'}>{__(license.status)}</Badge>
            </div>
            <div className="flex items-stretch gap-2">
                <code dir="ltr" className="flex-1 truncate rounded-md border bg-background px-3 py-2 font-mono text-sm">
                    {license.key}
                </code>
                <Button type="button" size="sm" variant="outline" onClick={copy} className="shrink-0">
                    {copied ? <CheckCircle2 className="text-emerald-600" /> : <Copy />}
                    {copied ? __('Copied') : __('Copy')}
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                {__(':used of :limit activations used', { used: license.activations_used, limit: license.activation_limit })}
                {license.expires_at && <> · {__('Valid until :date', { date: new Date(license.expires_at).toLocaleDateString() })}</>}
            </p>
        </div>
    );
}
