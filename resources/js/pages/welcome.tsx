import { Button } from '@/components/ui/button';
import { Container, Eyebrow, Section, SectionHeading } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    Cloud,
    Code2,
    CreditCard,
    FileDown,
    Fingerprint,
    Globe2,
    KeyRound,
    Layers,
    Lock,
    type LucideIcon,
    Minus,
    Plus,
    ShieldCheck,
    Sparkles,
    Workflow,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

interface HomePartner {
    id: number;
    name: string;
    logo_url: string | null;
    website_url: string | null;
}

export default function Welcome({ partners = [], faqs = [] }: { partners?: HomePartner[]; faqs?: FAQShape[] }) {
    const { auth, branding } = usePage<SharedData>().props;
    const brandTitle = branding?.title ?? 'StoreProject';

    return (
        <StorefrontLayout>
            <Head title={`${brandTitle} — Premium marketplace for makers`} />

            <Hero authed={Boolean(auth?.user)} />
            <LogoCloud partners={partners} />
            <FeatureGrid />
            <ProductTypes />
            <Pricing />
            <Testimonials />
            <FAQ faqs={faqs} />
            <CTAStrip authed={Boolean(auth?.user)} />
        </StorefrontLayout>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  HERO                                                                      */
/* ────────────────────────────────────────────────────────────────────────── */

function Hero({ authed }: { authed: boolean }) {
    const { t, direction } = useTranslate();
    const arrow = direction === 'rtl' ? '←' : '→';

    return (
        <Section className="overflow-hidden pt-20 pb-16 sm:pt-28 sm:pb-20 lg:pt-32">
            <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                <div className="bg-spotlight absolute inset-x-0 top-0 h-[600px]" />
                <div className="bg-grid mask-fade-b absolute inset-0 opacity-[0.35]" />
            </div>

            <Container>
                <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
                    <Eyebrow>
                        <Sparkles className="size-3" />
                        <span>{t('hero.eyebrow_beta')}</span>
                        <span className="text-foreground/70">·</span>
                        <span className="text-foreground/80 font-sans tracking-normal normal-case">{t('hero.eyebrow_release')}</span>
                    </Eyebrow>

                    <h1 className="font-display text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[64px]">
                        {t('hero.title_lead')}{' '}
                        <span className="relative whitespace-nowrap">
                            <span className="from-primary relative z-10 bg-gradient-to-r via-fuchsia-500 to-rose-500 bg-clip-text text-transparent">
                                {t('hero.title_highlight')}
                            </span>
                            <span aria-hidden className="bg-primary/10 dark:bg-primary/15 absolute inset-x-0 bottom-1 -z-0 h-[10px]" />
                        </span>
                    </h1>

                    <p className="text-muted-foreground max-w-2xl text-base text-pretty sm:text-lg">{t('hero.subtitle')}</p>

                    <div className="mt-2 flex flex-col items-center gap-3 sm:flex-row">
                        <Button asChild size="lg">
                            <Link href={authed ? route('dashboard') : route('register')}>
                                {authed ? t('common.open_dashboard') : t('hero.cta_primary')}
                                <span aria-hidden>{arrow}</span>
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href={route('products.index')}>{t('hero.cta_secondary')}</Link>
                        </Button>
                    </div>

                    <ul className="text-muted-foreground mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs">
                        {[t('hero.trust_no_card'), t('hero.trust_payments'), t('hero.trust_uptime')].map((item) => (
                            <li key={item} className="inline-flex items-center gap-1.5">
                                <CheckCircle2 className="text-foreground/70 size-3.5" />
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative mx-auto mt-16 max-w-5xl">
                    <div aria-hidden className="from-foreground/20 via-foreground/5 absolute -inset-px rounded-2xl bg-gradient-to-b to-transparent" />
                    <div className="border-border/80 bg-card shadow-foreground/[0.06] relative overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="border-border/60 bg-muted/40 flex items-center gap-1.5 border-b px-4 py-2.5">
                            <div className="flex items-center gap-1.5">
                                <span className="bg-border size-2.5 rounded-full" />
                                <span className="bg-border size-2.5 rounded-full" />
                                <span className="bg-border size-2.5 rounded-full" />
                            </div>
                            <div className="border-border/60 bg-background text-muted-foreground ms-3 hidden items-center gap-1.5 rounded-md border px-2.5 py-1 font-mono text-[11px] sm:flex">
                                <Lock className="size-3" />
                                store.yourbrand.com
                            </div>
                        </div>

                        <div className="grid gap-0 sm:grid-cols-[200px_1fr]">
                            <aside className="border-border/60 bg-muted/20 hidden flex-col gap-1 border-e p-4 text-sm sm:flex">
                                {[
                                    { icon: BarChart3, key: 'overview', label: 'Overview', active: true },
                                    { icon: Layers, key: 'products', label: 'Products' },
                                    { icon: KeyRound, key: 'licenses', label: 'Licenses' },
                                    { icon: CreditCard, key: 'orders', label: 'Orders' },
                                    { icon: Workflow, key: 'subscriptions', label: 'Subscriptions' },
                                    { icon: ShieldCheck, key: 'settings', label: 'Settings' },
                                ].map((item) => (
                                    <div
                                        key={item.key}
                                        className={cn(
                                            'flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px]',
                                            item.active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground',
                                        )}
                                    >
                                        <item.icon className="size-3.5" />
                                        {t(`hero.preview_nav.${item.key}`)}
                                    </div>
                                ))}
                            </aside>

                            <div className="p-5 sm:p-6">
                                <div className="mb-5 flex items-end justify-between">
                                    <div>
                                        <p className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">
                                            {t('hero.preview_revenue')}
                                        </p>
                                        <p className="font-display mt-1 text-2xl font-semibold tabular-nums sm:text-3xl">
                                            $48,392<span className="text-muted-foreground ms-1 text-base font-normal">.21</span>
                                        </p>
                                    </div>
                                    <div className="border-border/80 hidden items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium text-emerald-600 sm:inline-flex dark:text-emerald-400">
                                        ↑ 23.4%
                                    </div>
                                </div>

                                <div className="mb-5 grid h-24 grid-cols-12 items-end gap-1.5 sm:h-28">
                                    {[40, 65, 50, 80, 55, 90, 70, 95, 60, 85, 75, 100].map((h, i) => (
                                        <div
                                            key={i}
                                            className="from-primary/70 to-primary hover:from-primary rounded-sm bg-gradient-to-t transition-all duration-500 hover:to-fuchsia-500"
                                            style={{ height: `${h}%` }}
                                        />
                                    ))}
                                </div>

                                <div className="border-border/60 rounded-lg border">
                                    {[
                                        { id: '#ORD-2841', name: 'Stripe Toolkit Pro', amount: '$129.00', status: 'Paid' },
                                        { id: '#ORD-2840', name: 'CRM Boilerplate', amount: '$49.00', status: 'Paid' },
                                        { id: '#ORD-2839', name: 'API Credits · 5k', amount: '$199.00', status: 'Trial' },
                                    ].map((order, i, arr) => (
                                        <div
                                            key={order.id}
                                            className={cn(
                                                'grid grid-cols-[auto_1fr_auto_auto] items-center gap-3 px-4 py-2.5 text-[13px]',
                                                i < arr.length - 1 && 'border-border/60 border-b',
                                            )}
                                        >
                                            <span className="text-muted-foreground font-mono text-[11px]">{order.id}</span>
                                            <span className="truncate font-medium">{order.name}</span>
                                            <span className="tabular-nums">{order.amount}</span>
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                    order.status === 'Paid'
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                        : 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
                                                )}
                                            >
                                                {t(`hero.preview_status.${order.status.toLowerCase()}`)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  LOGO CLOUD                                                                */
/* ────────────────────────────────────────────────────────────────────────── */

function LogoCloud({ partners }: { partners: HomePartner[] }) {
    const { t } = useTranslate();
    // Managed in Admin → Partners; the strip disappears when none are shown.
    if (partners.length === 0) return null;
    const columns = Math.min(partners.length, 6);
    return (
        <section className="border-border/60 bg-muted/20 border-y py-12">
            <Container>
                <p className="text-muted-foreground mb-8 text-center text-xs font-medium tracking-widest uppercase">{t('logo_cloud.tagline')}</p>
                <div
                    className={cn(
                        'grid grid-cols-2 items-center justify-items-center gap-x-12 gap-y-6 sm:grid-cols-3',
                        {
                            1: 'lg:grid-cols-1',
                            2: 'lg:grid-cols-2',
                            3: 'lg:grid-cols-3',
                            4: 'lg:grid-cols-4',
                            5: 'lg:grid-cols-5',
                            6: 'lg:grid-cols-6',
                        }[columns],
                    )}
                >
                    {partners.map((partner) => {
                        const mark = partner.logo_url ? (
                            <img
                                src={partner.logo_url}
                                alt={partner.name}
                                loading="lazy"
                                className="h-10 w-auto max-w-[160px] object-contain opacity-80 grayscale transition hover:opacity-100 hover:grayscale-0 sm:h-12"
                            />
                        ) : (
                            <span className="font-display text-muted-foreground/70 hover:text-foreground text-base font-semibold tracking-[0.2em] uppercase transition-colors">
                                {partner.name}
                            </span>
                        );
                        return partner.website_url ? (
                            <a key={partner.id} href={partner.website_url} target="_blank" rel="noopener noreferrer" title={partner.name}>
                                {mark}
                            </a>
                        ) : (
                            <span key={partner.id}>{mark}</span>
                        );
                    })}
                </div>
            </Container>
        </section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  FEATURE GRID                                                              */
/* ────────────────────────────────────────────────────────────────────────── */

const FEATURE_ICONS: { key: string; icon: LucideIcon }[] = [
    { key: 'licenses', icon: KeyRound },
    { key: 'downloads', icon: FileDown },
    { key: 'billing', icon: CreditCard },
    { key: 'analytics', icon: BarChart3 },
    { key: 'security', icon: ShieldCheck },
    { key: 'seo', icon: Globe2 },
];

function FeatureGrid() {
    const { t } = useTranslate();
    return (
        <Section>
            <Container>
                <SectionHeading
                    eyebrow={
                        <>
                            <Zap className="size-3" /> {t('features.eyebrow')}
                        </>
                    }
                    title={t('features.title')}
                    description={t('features.description')}
                />

                <div className="border-border/60 bg-border/60 mt-16 grid grid-cols-1 gap-px overflow-hidden rounded-xl border sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURE_ICONS.map(({ key, icon: Icon }) => (
                        <div key={key} className="group bg-card hover:bg-muted/40 relative p-6 transition-colors duration-200 sm:p-8">
                            <div className="border-border/80 bg-background text-foreground group-hover:border-foreground/30 mb-5 inline-flex size-10 items-center justify-center rounded-lg border transition-colors duration-200">
                                <Icon className="size-4" />
                            </div>
                            <h3 className="font-display mb-2 text-base font-semibold tracking-tight">{t(`features.items.${key}.title`)}</h3>
                            <p className="text-muted-foreground text-sm leading-relaxed">{t(`features.items.${key}.body`)}</p>
                        </div>
                    ))}
                </div>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  PRODUCT TYPES                                                             */
/* ────────────────────────────────────────────────────────────────────────── */

const PRODUCT_TYPE_ICONS: { key: string; icon: LucideIcon }[] = [
    { key: 'downloads', icon: FileDown },
    { key: 'subscriptions', icon: CreditCard },
    { key: 'api', icon: Code2 },
    { key: 'licenses', icon: Fingerprint },
];

function ProductTypes() {
    const { t } = useTranslate();
    return (
        <Section className="border-border/60 bg-muted/20 border-t">
            <Container>
                <SectionHeading
                    eyebrow={
                        <>
                            <Layers className="size-3" /> {t('product_types.eyebrow')}
                        </>
                    }
                    title={t('product_types.title')}
                    description={t('product_types.description')}
                />

                <div className="mt-16 grid gap-4 sm:grid-cols-2">
                    {PRODUCT_TYPE_ICONS.map(({ key, icon: Icon }) => (
                        <div
                            key={key}
                            className="group border-border/60 bg-card hover:shadow-foreground/[0.04] relative overflow-hidden rounded-xl border p-6 transition-shadow hover:shadow-lg sm:p-7"
                        >
                            <div
                                aria-hidden
                                className="bg-foreground/[0.02] dark:bg-foreground/[0.06] absolute -end-12 -top-12 size-40 rounded-full blur-2xl transition-all group-hover:scale-110"
                            />
                            <div className="relative flex items-start gap-4">
                                <div className="border-border bg-background flex size-10 shrink-0 items-center justify-center rounded-lg border">
                                    <Icon className="size-4" />
                                </div>
                                <div className="space-y-1">
                                    <h3 className="font-display text-base font-semibold tracking-tight">{t(`product_types.items.${key}.label`)}</h3>
                                    <p className="text-muted-foreground text-sm leading-relaxed">{t(`product_types.items.${key}.desc`)}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  PRICING                                                                   */
/* ────────────────────────────────────────────────────────────────────────── */

interface PlanShape {
    name: string;
    price: string;
    cadence: string;
    description: string;
    cta: string;
    features: string[];
}

function Pricing() {
    const { t, tList } = useTranslate();
    const planKeys: { id: 'starter' | 'pro' | 'scale'; featured: boolean }[] = [
        { id: 'starter', featured: false },
        { id: 'pro', featured: true },
        { id: 'scale', featured: false },
    ];

    return (
        <Section id="pricing">
            <Container>
                <SectionHeading
                    eyebrow={
                        <>
                            <Cloud className="size-3" /> {t('pricing.eyebrow')}
                        </>
                    }
                    title={t('pricing.title')}
                    description={t('pricing.description')}
                />

                <div className="mt-16 grid gap-4 lg:grid-cols-3">
                    {planKeys.map(({ id, featured }) => {
                        const plan = tList<PlanShape>(`pricing.plans.${id}`);
                        const features: string[] = Array.isArray(plan?.features) ? plan.features : [];
                        return (
                            <div
                                key={id}
                                className={cn(
                                    'bg-card relative flex flex-col rounded-2xl border p-6 sm:p-8',
                                    featured ? 'border-foreground/40 shadow-foreground/[0.06] shadow-xl lg:-mt-4 lg:mb-0' : 'border-border/60',
                                )}
                            >
                                {featured && (
                                    <div className="from-primary shadow-primary/30 absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r to-fuchsia-500 px-3 py-1 font-mono text-[10px] tracking-wider text-white uppercase shadow-lg">
                                        {t('pricing.most_popular')}
                                    </div>
                                )}

                                <div className="mb-6">
                                    <h3 className="font-display text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                                        {plan?.name}
                                    </h3>
                                    <div className="mt-3 flex items-baseline gap-1">
                                        <span className="font-display text-4xl font-semibold tracking-tight">{plan?.price}</span>
                                        <span className="text-muted-foreground text-sm">{plan?.cadence}</span>
                                    </div>
                                    <p className="text-muted-foreground mt-2 text-sm">{plan?.description}</p>
                                </div>

                                <ul className="mb-8 flex-1 space-y-3 text-sm">
                                    {features.map((feat) => (
                                        <li key={feat} className="flex items-start gap-2.5">
                                            <CheckCircle2 className="text-primary mt-0.5 size-4 shrink-0" />
                                            <span>{feat}</span>
                                        </li>
                                    ))}
                                </ul>

                                <Button asChild size="lg" variant={featured ? 'default' : 'outline'} className="w-full">
                                    <Link href={route('register')}>{plan?.cta}</Link>
                                </Button>
                            </div>
                        );
                    })}
                </div>

                <p className="text-muted-foreground mt-8 text-center text-xs">{t('pricing.footnote')}</p>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  TESTIMONIALS                                                              */
/* ────────────────────────────────────────────────────────────────────────── */

interface TestimonialShape {
    quote: string;
    name: string;
    role: string;
}

function Testimonials() {
    const { t, tList } = useTranslate();
    const items = tList<TestimonialShape[]>('testimonials.items') ?? [];
    return (
        <Section id="testimonials" className="border-border/60 border-t">
            <Container>
                <SectionHeading eyebrow={t('testimonials.eyebrow')} title={t('testimonials.title')} description={t('testimonials.description')} />

                <div className="mt-16 grid gap-4 md:grid-cols-3">
                    {items.map((tm, i) => (
                        <figure key={i} className="border-border/60 bg-card flex flex-col gap-6 rounded-xl border p-6 sm:p-7">
                            <div aria-hidden className="font-display text-foreground/15 text-3xl leading-none">
                                “
                            </div>
                            <blockquote className="text-foreground/90 flex-1 text-[15px] leading-relaxed text-pretty">{tm.quote}</blockquote>
                            <figcaption className="border-border/60 flex items-center gap-3 border-t pt-4">
                                <div className="bg-foreground text-background flex size-9 items-center justify-center rounded-full text-xs font-semibold">
                                    {tm.name
                                        .split(' ')
                                        .map((n) => n[0])
                                        .join('')
                                        .slice(0, 2)}
                                </div>
                                <div className="text-sm">
                                    <div className="font-medium">{tm.name}</div>
                                    <div className="text-muted-foreground text-xs">{tm.role}</div>
                                </div>
                            </figcaption>
                        </figure>
                    ))}
                </div>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  FAQ                                                                       */
/* ────────────────────────────────────────────────────────────────────────── */

interface FAQShape {
    id: number;
    q: string;
    a: string;
}

function FAQ({ faqs }: { faqs: FAQShape[] }) {
    const { t } = useTranslate();
    const [open, setOpen] = useState<number | null>(0);
    // Managed in Admin → FAQ; the section disappears when none are shown.
    if (faqs.length === 0) return null;
    return (
        <Section id="faq" className="border-border/60 bg-muted/20 border-t">
            <Container>
                <SectionHeading eyebrow={t('faq.eyebrow')} title={t('faq.title')} description={t('faq.description')} />

                <div className="divide-border/60 border-border/60 bg-card mx-auto mt-16 max-w-3xl divide-y overflow-hidden rounded-xl border">
                    {faqs.map((faq, i) => {
                        const isOpen = open === i;
                        return (
                            <div key={faq.id}>
                                <button
                                    type="button"
                                    onClick={() => setOpen(isOpen ? null : i)}
                                    className="hover:bg-muted/40 flex w-full items-center justify-between gap-4 px-5 py-4 text-start text-sm transition-colors sm:px-6 sm:py-5"
                                    aria-expanded={isOpen}
                                >
                                    <span className="font-medium">{faq.q}</span>
                                    <span className="border-border/80 text-muted-foreground flex size-6 shrink-0 items-center justify-center rounded-full border">
                                        {isOpen ? <Minus className="size-3" /> : <Plus className="size-3" />}
                                    </span>
                                </button>
                                <div
                                    className={cn(
                                        'grid overflow-hidden transition-[grid-template-rows] duration-200 ease-out',
                                        isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                    )}
                                >
                                    <div className="min-h-0">
                                        <p className="text-muted-foreground px-5 pb-5 text-sm leading-relaxed sm:px-6 sm:pb-6">{faq.a}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  CTA STRIP                                                                 */
/* ────────────────────────────────────────────────────────────────────────── */

function CTAStrip({ authed }: { authed: boolean }) {
    const { t, direction } = useTranslate();
    const arrow = direction === 'rtl' ? '←' : '→';
    return (
        <Section className="border-border/60 border-t pt-20 pb-24 sm:pb-32">
            <Container>
                <div className="border-primary/20 shadow-primary/30 relative isolate overflow-hidden rounded-2xl border bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-10 text-white shadow-2xl sm:p-16 dark:from-indigo-500 dark:via-violet-500 dark:to-fuchsia-500">
                    <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                        <div className="bg-grid absolute inset-0 opacity-[0.18]" />
                        <div className="absolute -end-32 -top-32 size-72 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -start-20 -bottom-24 size-64 rounded-full bg-fuchsia-300/30 blur-3xl" />
                    </div>
                    <div className="mx-auto flex max-w-xl flex-col items-center gap-5 text-center">
                        <h2 className="font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{t('cta_strip.title')}</h2>
                        <p className="text-base text-pretty text-white/85">{t('cta_strip.body')}</p>
                        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                            <Button asChild size="lg" className="border-transparent bg-white text-indigo-700 shadow-md hover:bg-white/90">
                                <Link href={authed ? route('dashboard') : route('register')}>
                                    {authed ? t('common.go_to_dashboard') : t('cta_strip.primary')}
                                    <span aria-hidden>{arrow}</span>
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="ghost" className="text-white hover:bg-white/10 hover:text-white">
                                <Link href={route('products.index')}>{t('cta_strip.secondary')}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </Container>
        </Section>
    );
}
