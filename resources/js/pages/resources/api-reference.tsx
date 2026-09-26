import { Container, Section } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';

interface EndpointParam {
    name: string;
    required: boolean;
}

interface Endpoint {
    key: string;
    method: string;
    path: string;
    params: EndpointParam[];
}

interface EndpointGroup {
    key: string;
    auth: 'license_key' | 'token';
    endpoints: Endpoint[];
}

interface PageProps {
    baseUrl: string;
    groups: EndpointGroup[];
    licenseExample: Record<string, unknown>;
    licenseErrors: { code: string; status: number; message: string }[];
    rateLimitPerMinute: number;
}

const EXAMPLE_VALUES: Record<string, string> = {
    license_key: 'ABCD-EFGH-IJKL-MNOP',
    domain: 'example.com',
    product_id: '42',
    status: 'unread',
    per_page: '25',
};

const METHOD_STYLES: Record<string, string> = {
    GET: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    POST: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    PATCH: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    DELETE: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
};

/** A curl call built from the endpoint definition, so it matches the router. */
function curlExample(baseUrl: string, endpoint: Endpoint, auth: EndpointGroup['auth']): string {
    const required = endpoint.params.filter((p) => p.required);
    const values = Object.fromEntries(required.map((p) => [p.name, EXAMPLE_VALUES[p.name] ?? '…']));
    const lines: string[] = [];

    if (endpoint.method === 'GET') {
        const query = new URLSearchParams(values).toString();
        lines.push(`curl "${baseUrl}${endpoint.path}${query ? `?${query}` : ''}"`);
    } else {
        lines.push(`curl -X ${endpoint.method} "${baseUrl}${endpoint.path}"`);
    }

    if (auth === 'token') {
        lines.push('  -H "Authorization: Bearer <token>"');
    }
    lines.push('  -H "Accept: application/json"');

    if (endpoint.method !== 'GET' && required.length > 0) {
        lines.push('  -H "Content-Type: application/json"');
        lines.push(`  -d '${JSON.stringify(values)}'`);
    }

    return lines.join(' \\\n');
}

function CodeBlock({ children }: { children: string }) {
    return (
        <pre dir="ltr" className="bg-muted/50 border-border/60 overflow-x-auto rounded-lg border p-4 text-left font-mono text-xs leading-relaxed">
            <code>{children}</code>
        </pre>
    );
}

export default function ApiReference({ baseUrl, groups, licenseExample, licenseErrors, rateLimitPerMinute }: PageProps) {
    const { branding } = usePage<SharedData>().props;
    const { t } = useTranslate();
    const brandTitle = branding?.title ?? 'StoreProject';

    return (
        <StorefrontLayout>
            <Head title={`${t('api_reference.meta_title')} — ${brandTitle}`} />

            <Section className="pt-16 pb-24 sm:pt-20 sm:pb-32">
                <Container>
                    <div className="mx-auto max-w-4xl">
                        <header className="flex flex-col gap-3">
                            <span className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">{t('api_reference.eyebrow')}</span>
                            <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                {t('api_reference.title')}
                            </h1>
                            <p className="text-muted-foreground text-pretty sm:text-lg">{t('api_reference.lead')}</p>
                        </header>

                        {/* Base URL */}
                        <div className="border-border/60 mt-10 rounded-xl border p-5">
                            <h2 className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">
                                {t('api_reference.base_url.title')}
                            </h2>
                            <p dir="ltr" className="mt-2 text-left font-mono text-sm break-all">
                                {baseUrl}/api/v1
                            </p>
                            <p className="text-muted-foreground mt-2 text-sm">{t('api_reference.base_url.note')}</p>
                        </div>

                        {/* Authentication */}
                        <section className="mt-12">
                            <h2 className="font-display text-2xl font-semibold tracking-tight">{t('api_reference.auth.title')}</h2>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div className="border-border/60 rounded-xl border p-5">
                                    <KeyRound className="text-primary size-5" />
                                    <h3 className="mt-3 font-semibold">{t('api_reference.auth.license_key.title')}</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">{t('api_reference.auth.license_key.body')}</p>
                                </div>
                                <div className="border-border/60 rounded-xl border p-5">
                                    <ShieldCheck className="text-primary size-5" />
                                    <h3 className="mt-3 font-semibold">{t('api_reference.auth.token.title')}</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">{t('api_reference.auth.token.body')}</p>
                                    <Link
                                        href={route('api-tokens.index')}
                                        className="text-primary mt-2 inline-block text-sm font-medium hover:underline"
                                    >
                                        {t('api_reference.auth.token.link')}
                                    </Link>
                                </div>
                            </div>
                        </section>

                        {/* Endpoint groups */}
                        {groups.map((group) => (
                            <section key={group.key} id={group.key} className="mt-16 scroll-mt-24">
                                <h2 className="font-display text-2xl font-semibold tracking-tight">{t(`api_reference.groups.${group.key}.title`)}</h2>
                                <p className="text-muted-foreground mt-2">{t(`api_reference.groups.${group.key}.description`)}</p>

                                <div className="mt-6 space-y-6">
                                    {group.endpoints.map((endpoint) => (
                                        <article key={endpoint.key} id={endpoint.key} className="border-border/60 scroll-mt-24 rounded-xl border p-5">
                                            <div dir="ltr" className="flex flex-wrap items-center gap-2 text-left">
                                                <span
                                                    className={cn(
                                                        'rounded-md px-2 py-0.5 font-mono text-xs font-semibold',
                                                        METHOD_STYLES[endpoint.method] ?? 'bg-muted text-foreground',
                                                    )}
                                                >
                                                    {endpoint.method}
                                                </span>
                                                <code className="font-mono text-sm break-all">{endpoint.path}</code>
                                            </div>
                                            <p className="mt-3 text-sm">{t(`api_reference.endpoints.${endpoint.key}`)}</p>

                                            {endpoint.params.length > 0 && (
                                                <div className="mt-4 overflow-x-auto">
                                                    <table className="w-full text-sm">
                                                        <caption className="text-muted-foreground mb-2 text-start font-mono text-[11px] tracking-wider uppercase">
                                                            {endpoint.method === 'GET'
                                                                ? t('api_reference.params.in_query')
                                                                : t('api_reference.params.in_body')}
                                                        </caption>
                                                        <tbody className="divide-border/60 divide-y">
                                                            {endpoint.params.map((param) => (
                                                                <tr key={param.name}>
                                                                    <td className="py-2 pe-4 align-top font-mono text-xs whitespace-nowrap" dir="ltr">
                                                                        {param.name}
                                                                    </td>
                                                                    <td className="text-muted-foreground py-2 pe-4 align-top text-xs whitespace-nowrap">
                                                                        {param.required
                                                                            ? t('api_reference.params.required')
                                                                            : t('api_reference.params.optional')}
                                                                    </td>
                                                                    <td className="text-muted-foreground py-2 align-top">
                                                                        {t(`api_reference.params.${param.name}`)}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}

                                            <div className="mt-4">
                                                <CodeBlock>{curlExample(baseUrl, endpoint, group.auth)}</CodeBlock>
                                            </div>
                                        </article>
                                    ))}
                                </div>

                                {group.key === 'licenses' && (
                                    <div className="mt-6">
                                        <h3 className="font-semibold">{t('api_reference.response.title')}</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">{t('api_reference.response.body')}</p>
                                        <div className="mt-3">
                                            <CodeBlock>{JSON.stringify(licenseExample, null, 2)}</CodeBlock>
                                        </div>
                                    </div>
                                )}
                            </section>
                        ))}

                        {/* Errors */}
                        <section id="errors" className="mt-16 scroll-mt-24">
                            <h2 className="font-display text-2xl font-semibold tracking-tight">{t('api_reference.errors.title')}</h2>
                            <p className="text-muted-foreground mt-2">{t('api_reference.errors.body')}</p>
                            <div className="mt-4">
                                <CodeBlock>
                                    {JSON.stringify({ message: licenseErrors[0]?.message, error: licenseErrors[0]?.code }, null, 2)}
                                </CodeBlock>
                            </div>

                            <div className="mt-6 overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-muted-foreground text-start font-mono text-[11px] tracking-wider uppercase">
                                        <tr>
                                            <th className="py-2 pe-4 text-start font-medium">{t('api_reference.errors.status')}</th>
                                            <th className="py-2 pe-4 text-start font-medium">{t('api_reference.errors.code')}</th>
                                            <th className="py-2 text-start font-medium">{t('api_reference.errors.meaning')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border/60 divide-y">
                                        {licenseErrors.map((error) => (
                                            <tr key={error.code}>
                                                <td className="py-2 pe-4 align-top font-mono text-xs">{error.status}</td>
                                                <td className="py-2 pe-4 align-top font-mono text-xs" dir="ltr">
                                                    {error.code}
                                                </td>
                                                <td className="text-muted-foreground py-2 align-top">
                                                    {t(`api_reference.errors.codes.${error.code}`)}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr>
                                            <td className="py-2 pe-4 align-top font-mono text-xs">422</td>
                                            <td className="py-2 pe-4 align-top font-mono text-xs">—</td>
                                            <td className="text-muted-foreground py-2 align-top">{t('api_reference.errors.validation')}</td>
                                        </tr>
                                        <tr>
                                            <td className="py-2 pe-4 align-top font-mono text-xs">429</td>
                                            <td className="py-2 pe-4 align-top font-mono text-xs">—</td>
                                            <td className="text-muted-foreground py-2 align-top">
                                                {t('api_reference.errors.rate_limit', { count: rateLimitPerMinute })}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
