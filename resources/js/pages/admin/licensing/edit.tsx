import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { FormEvent } from 'react';

interface Props {
    settings: { extended_enabled: boolean; extended_multiplier: number };
    counts: { products: number; own_price: number };
    example: { id: number; title: string; price: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Licensing', href: '/admin/licensing' },
];

function money(value: number) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
}

export default function AdminLicensing({ settings, counts, example }: Props) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null } }>().props;

    const form = useForm({
        extended_enabled: settings.extended_enabled,
        extended_multiplier: String(settings.extended_multiplier),
    });

    const save = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('admin.licensing.update'), { preserveScroll: true });
    };

    const multiplier = parseFloat(form.data.extended_multiplier) || 0;
    const covered = counts.products - counts.own_price;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Licensing')} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="inline-flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            <KeyRound className="size-5" /> {__('Licensing')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {__('Offer an Extended License on your products — for buyers who build something their own users pay for.')}
                        </p>
                    </div>
                    <Badge variant={settings.extended_enabled ? 'default' : 'outline'}>
                        {settings.extended_enabled ? __('Extended License on') : __('Extended License off')}
                    </Badge>
                </div>

                <form onSubmit={save} className="space-y-6 rounded-lg border bg-card p-6">
                    <label className="flex items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.extended_enabled}
                            onChange={(e) => form.setData('extended_enabled', e.target.checked)}
                            className="mt-0.5 size-4 rounded border-input"
                        />
                        <span>
                            <span className="block font-medium">{__('Offer an Extended License on every product')}</span>
                            <span className="block text-xs text-muted-foreground">
                                {__('Products with their own Extended price keep it. Subscriptions never offer one.')}
                            </span>
                        </span>
                    </label>

                    <div className="max-w-xs">
                        <Label htmlFor="extended_multiplier" className="mb-1 block text-xs">
                            {__('Extended price = Regular price ×')}
                        </Label>
                        <Input
                            id="extended_multiplier"
                            type="number"
                            min={1}
                            max={100}
                            step="0.5"
                            value={form.data.extended_multiplier}
                            onChange={(e) => form.setData('extended_multiplier', e.target.value)}
                            disabled={!form.data.extended_enabled}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">{__('Marketplaces usually charge about 5× the Regular price.')}</p>
                        <InputError message={form.errors.extended_multiplier} className="mt-1" />
                    </div>

                    {form.data.extended_enabled && multiplier > 0 && (
                        <div className="space-y-1 rounded-md border border-dashed bg-muted/30 p-4 text-sm">
                            {example && (
                                <p>
                                    {__('Example — :title:', { title: example.title })}{' '}
                                    <span className="tabular-nums">
                                        {__('Regular')} {money(parseFloat(example.price))} → {__('Extended')}{' '}
                                        <strong>{money(parseFloat(example.price) * multiplier)}</strong>
                                    </span>
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {__(':covered products will use this rule; :own have their own Extended price.', { covered, own: counts.own_price })}
                            </p>
                        </div>
                    )}

                    <div className="flex justify-end border-t pt-4">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? __('Saving…') : __('Save settings')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
