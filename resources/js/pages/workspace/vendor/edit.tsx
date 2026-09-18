import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Vendor } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeCheck, ExternalLink, Store } from 'lucide-react';
import { FormEvent } from 'react';

interface VendorEditProps {
    vendor: Vendor | null;
    storeUrl: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Vendor store', href: '/workspace/vendor' }];

export default function VendorEdit({ vendor, storeUrl }: VendorEditProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor store" />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
                {vendor ? <ProfileForm vendor={vendor} storeUrl={storeUrl} /> : <OpenStore />}
            </div>
        </AppLayout>
    );
}

function OpenStore() {
    const { data, setData, post, processing, errors } = useForm({ name: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('workspace.vendor.store'));
    };

    return (
        <div className="bg-card rounded-xl border p-8 text-center">
            <div className="bg-primary/10 text-primary mx-auto mb-4 flex size-12 items-center justify-center rounded-full">
                <Store className="size-6" />
            </div>
            <h1 className="font-display text-xl font-semibold tracking-tight">Open your store</h1>
            <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                Start selling on the marketplace. Pick a store name — you can add your logo, bio, and links next.
            </p>
            <form onSubmit={submit} className="mx-auto mt-6 flex max-w-sm flex-col gap-3 text-left">
                <div>
                    <Label htmlFor="name">Store name</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Acme Digital" autoFocus />
                    {errors.name && <p className="text-destructive mt-1 text-sm">{errors.name}</p>}
                </div>
                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Creating…' : 'Open store'}
                </Button>
            </form>
        </div>
    );
}

function ProfileForm({ vendor, storeUrl }: { vendor: Vendor; storeUrl: string | null }) {
    const p = vendor.profile;
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm<{
        _method: string;
        name: string;
        company_name: string;
        bio: string;
        website: string;
        contact_email: string;
        contact_phone: string;
        country: string;
        founded_year: string;
        logo: File | null;
        banner: File | null;
        social_links: Record<string, string>;
    }>({
        _method: 'put',
        name: vendor.name ?? '',
        company_name: p?.company_name ?? '',
        bio: p?.bio ?? '',
        website: p?.website ?? '',
        contact_email: p?.contact_email ?? '',
        contact_phone: p?.contact_phone ?? '',
        country: p?.country ?? '',
        founded_year: p?.founded_year ? String(p.founded_year) : '',
        logo: null,
        banner: null,
        social_links: {
            twitter: p?.social_links?.twitter ?? '',
            github: p?.social_links?.github ?? '',
            linkedin: p?.social_links?.linkedin ?? '',
        },
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('workspace.vendor.update'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="font-display text-xl font-semibold tracking-tight">{vendor.name}</h1>
                        {vendor.is_verified && <BadgeCheck className="text-primary size-5" />}
                    </div>
                    <p className="text-muted-foreground text-sm">Manage your public storefront.</p>
                </div>
                {storeUrl && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={storeUrl}>
                            View store
                            <ExternalLink className="ms-1.5 size-3.5" />
                        </Link>
                    </Button>
                )}
            </div>

            <Section title="Store details">
                <Field label="Store name" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="Company name" error={errors.company_name}>
                    <Input value={data.company_name} onChange={(e) => setData('company_name', e.target.value)} />
                </Field>
                <Field label="Bio" error={errors.bio}>
                    <textarea
                        value={data.bio}
                        onChange={(e) => setData('bio', e.target.value)}
                        rows={4}
                        className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Tell buyers what your store offers…"
                    />
                </Field>
            </Section>

            <Section title="Branding">
                <Field label="Logo (JPG/PNG/WebP)" error={errors.logo}>
                    <Input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setData('logo', e.target.files?.[0] ?? null)} />
                </Field>
                <Field label="Banner (JPG/PNG/WebP)" error={errors.banner}>
                    <Input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setData('banner', e.target.files?.[0] ?? null)} />
                </Field>
            </Section>

            <Section title="Contact & links">
                <Field label="Website" error={errors.website}>
                    <Input value={data.website} onChange={(e) => setData('website', e.target.value)} placeholder="https://" />
                </Field>
                <Field label="Contact email" error={errors.contact_email}>
                    <Input type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} />
                </Field>
                <Field label="Contact phone" error={errors.contact_phone}>
                    <Input value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Country (2-letter)" error={errors.country}>
                        <Input
                            value={data.country}
                            maxLength={2}
                            onChange={(e) => setData('country', e.target.value.toUpperCase())}
                            placeholder="US"
                        />
                    </Field>
                    <Field label="Founded year" error={errors.founded_year}>
                        <Input type="number" value={data.founded_year} onChange={(e) => setData('founded_year', e.target.value)} placeholder="2020" />
                    </Field>
                </div>
                {(['twitter', 'github', 'linkedin'] as const).map((key) => (
                    <Field key={key} label={`${key[0].toUpperCase()}${key.slice(1)} URL`}>
                        <Input
                            value={data.social_links[key]}
                            onChange={(e) => setData('social_links', { ...data.social_links, [key]: e.target.value })}
                            placeholder="https://"
                        />
                    </Field>
                ))}
            </Section>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : 'Save changes'}
                </Button>
                {recentlySuccessful && <span className="text-muted-foreground text-sm">Saved.</span>}
            </div>
        </form>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="bg-card rounded-xl border p-5">
            <h2 className="mb-4 text-sm font-semibold">{title}</h2>
            <div className="space-y-4">{children}</div>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <Label className="mb-1.5 block">{label}</Label>
            {children}
            {error && <p className="text-destructive mt-1 text-sm">{error}</p>}
        </div>
    );
}
