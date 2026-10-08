import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy, HardDrive, PlugZap } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Settings {
    enabled: boolean;
    provider: string;
    bucket: string;
    region: string;
    endpoint: string;
    key: string;
    has_secret: boolean;
    path_style: boolean;
}

interface Provider {
    value: string;
    label: string;
    endpoint: string | null;
    region: string;
}

interface Props {
    settings: Settings;
    providers: Provider[];
    cors: { s3: string; gcs: string };
    origin: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'File storage', href: '/admin/storage' },
];

/** Where each provider's keys come from — shown under the form. */
const HELP: Record<string, string[]> = {
    s3: [
        'In AWS, create a private S3 bucket (Block all public access: on).',
        'In IAM, create a user with s3:PutObject, s3:GetObject and s3:DeleteObject on that bucket, then create an access key for it.',
        'Region = the bucket’s region (e.g. eu-west-3). Leave the endpoint empty.',
    ],
    gcs: [
        'In Google Cloud Console → Cloud Storage, create a bucket (public access prevention: on).',
        'Cloud Storage → Settings → Interoperability: create an HMAC key for a service account that can read and write the bucket.',
        'Access key = the HMAC access ID, Secret = the HMAC secret. Endpoint: https://storage.googleapis.com, region: auto.',
    ],
    r2: [
        'In Cloudflare → R2, create a bucket.',
        'R2 → Manage API tokens: create a token with Object Read & Write; copy the S3 access key ID and secret.',
        'Endpoint: https://<ACCOUNT_ID>.r2.cloudflarestorage.com, region: auto.',
    ],
    spaces: [
        'In DigitalOcean, create a Space and note its region (e.g. fra1).',
        'API → Spaces Keys: generate a key and secret.',
        'The endpoint is filled in from the region.',
    ],
    custom: ['Use the endpoint, region and keys your provider gives for its S3-compatible API (MinIO, Wasabi, Backblaze B2 …).'],
};

export default function AdminFileStorage({ settings, providers, cors }: Props) {
    const { __ } = useTranslate();
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;
    const [testing, setTesting] = useState(false);

    const form = useForm({
        enabled: settings.enabled,
        provider: settings.provider,
        bucket: settings.bucket,
        region: settings.region,
        endpoint: settings.endpoint,
        key: settings.key,
        secret: '',
        path_style: settings.path_style,
    });

    const provider = providers.find((p) => p.value === form.data.provider) ?? providers[0];
    const corsRules = form.data.provider === 'gcs' ? cors.gcs : cors.s3;

    const save = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('admin.storage.update'), { preserveScroll: true, onSuccess: () => form.reset('secret') });
    };

    const test = () => {
        setTesting(true);
        router.post(route('admin.storage.test'), {}, { preserveScroll: true, onFinish: () => setTesting(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={__('File storage')} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-2 text-sm break-words text-destructive">{flash.error}</div>
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="inline-flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            <HardDrive className="size-5" /> {__('File storage')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {__('Where product files are kept. Cloud storage allows large files: they upload straight from the browser to your bucket.')}
                        </p>
                    </div>
                    <Badge variant={settings.enabled ? 'default' : 'outline'}>
                        {settings.enabled ? __('Cloud storage') : __('Server disk')}
                    </Badge>
                </div>

                <form onSubmit={save} className="space-y-6 rounded-lg border bg-card p-6">
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        {__('Store product files in cloud storage')}
                    </label>

                    <div>
                        <p className="mb-2 text-xs font-medium text-muted-foreground">{__('Provider')}</p>
                        <div className="flex flex-wrap gap-2">
                            {providers.map((p) => (
                                <Button
                                    key={p.value}
                                    type="button"
                                    size="sm"
                                    variant={form.data.provider === p.value ? 'default' : 'outline'}
                                    onClick={() => form.setData({ ...form.data, provider: p.value, region: p.region, endpoint: '' })}
                                >
                                    {__(p.label)}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={__('Bucket name')} error={form.errors.bucket}>
                            <Input dir="ltr" value={form.data.bucket} onChange={(e) => form.setData('bucket', e.target.value)} placeholder="my-store-files" />
                        </Field>
                        <Field label={__('Region')} error={form.errors.region}>
                            <Input dir="ltr" value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} placeholder={provider.region} />
                        </Field>
                        <Field label={__('Endpoint')} error={form.errors.endpoint} className="sm:col-span-2">
                            <Input
                                dir="ltr"
                                value={form.data.endpoint}
                                onChange={(e) => form.setData('endpoint', e.target.value)}
                                placeholder={provider.endpoint ?? (form.data.provider === 'r2' ? 'https://ACCOUNT_ID.r2.cloudflarestorage.com' : __('Leave empty for Amazon S3'))}
                            />
                        </Field>
                        <Field label={__('Access key')} error={form.errors.key}>
                            <Input dir="ltr" autoComplete="off" value={form.data.key} onChange={(e) => form.setData('key', e.target.value)} />
                        </Field>
                        <Field label={__('Secret key')} error={form.errors.secret}>
                            <Input
                                dir="ltr"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.secret}
                                onChange={(e) => form.setData('secret', e.target.value)}
                                placeholder={settings.has_secret ? __('•••••••• saved — leave blank to keep') : ''}
                            />
                        </Field>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.path_style}
                            onChange={(e) => form.setData('path_style', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        {__('Use path-style URLs (needed by MinIO and some S3-compatible services)')}
                    </label>

                    <ul className="list-disc space-y-1 rounded-md bg-muted/40 p-4 ps-8 text-xs text-muted-foreground">
                        {(HELP[form.data.provider] ?? HELP.custom).map((line) => (
                            <li key={line}>{__(line)}</li>
                        ))}
                    </ul>

                    <div className="flex flex-wrap justify-end gap-2 border-t pt-4">
                        <Button type="button" variant="outline" onClick={test} disabled={testing || form.isDirty}>
                            <PlugZap /> {testing ? __('Testing…') : __('Test connection')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? __('Saving…') : __('Save settings')}
                        </Button>
                    </div>
                </form>

                <CorsCard rules={corsRules} gcs={form.data.provider === 'gcs'} />
            </div>
        </AppLayout>
    );
}

function CorsCard({ rules, gcs }: { rules: string; gcs: boolean }) {
    const { __ } = useTranslate();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(rules);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <section className="space-y-3 rounded-lg border bg-card p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold">{__('Bucket CORS rules')}</h2>
                    <p className="text-xs text-muted-foreground">
                        {__('Browsers upload straight to the bucket, so it must allow this website. Without these rules uploads fail.')}
                    </p>
                </div>
                <Button type="button" size="sm" variant="outline" onClick={copy}>
                    {copied ? <CheckCircle2 className="text-emerald-600" /> : <Copy />}
                    {copied ? __('Copied') : __('Copy')}
                </Button>
            </div>
            <pre dir="ltr" className="overflow-x-auto rounded-md border bg-muted/40 p-3 font-mono text-xs">
                {rules}
            </pre>
            <p className="text-xs text-muted-foreground">
                {gcs
                    ? __('Save it as cors.json, then run: gcloud storage buckets update gs://YOUR_BUCKET --cors-file=cors.json')
                    : __('Paste it in the bucket’s CORS settings (AWS: bucket → Permissions → CORS; R2: bucket → Settings → CORS policy).')}
            </p>
        </section>
    );
}

function Field({ label, error, className, children }: { label: string; error?: string; className?: string; children: React.ReactNode }) {
    return (
        <div className={className}>
            <Label className="mb-1 block text-xs">{label}</Label>
            {children}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
