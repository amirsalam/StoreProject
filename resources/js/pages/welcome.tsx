import { Button } from '@/components/ui/button';
import { Container, Eyebrow, Section, SectionHeading } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
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

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <StorefrontLayout>
            <Head title="StoreProject — Premium marketplace for makers" />

            <Hero authed={Boolean(auth?.user)} />
            <LogoCloud />
            <FeatureGrid />
            <ProductTypes />
            <Pricing />
            <Testimonials />
            <FAQ />
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
        <Section className="overflow-hidden pt-20 sm:pt-28 lg:pt-32 pb-16 sm:pb-20">
            <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                <div className="absolute inset-x-0 top-0 h-[600px] bg-spotlight" />
                <div className="absolute inset-0 bg-grid opacity-[0.35] mask-fade-b" />
            </div>

            <Container>
                <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
                    <Eyebrow>
                        <Sparkles className="size-3" />
                        <span>{t('hero.eyebrow_beta')}</span>
                        <span className="text-foreground/70">·</span>
                        <span className="font-sans normal-case tracking-normal text-foreground/80">
                            {t('hero.eyebrow_release')}
                        </span>
                    </Eyebrow>

                    <h1 className="text-balance font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-[64px]">
                        {t('hero.title_lead')}{' '}
                        <span className="relative whitespace-nowrap">
                            <span className="relative z-10 bg-gradient-to-r from-primary via-fuchsia-500 to-rose-500 bg-clip-text text-transparent">
                                {t('hero.title_highlight')}
                            </span>
                            <span
                                aria-hidden
                                className="absolute inset-x-0 bottom-1 -z-0 h-[10px] bg-primary/10 dark:bg-primary/15"
                            />
                        </span>
                    </h1>

                    <p className="text-pretty max-w-2xl text-base text-muted-foreground sm:text-lg">
                        {t('hero.subtitle')}
                    </p>

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

                    <ul className="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-muted-foreground">
                        {[
                            t('hero.trust_no_card'),
                            t('hero.trust_payments'),
                            t('hero.trust_uptime'),
                        ].map((item) => (
                            <li key={item} className="inline-flex items-center gap-1.5">
                                <CheckCircle2 className="size-3.5 text-foreground/70" />
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative mx-auto mt-16 max-w-5xl">
                    <div
                        aria-hidden
                        className="absolute -inset-px rounded-2xl bg-gradient-to-b from-foreground/20 via-foreground/5 to-transparent"
                    />
                    <div className="relative overflow-hidden rounded-2xl border border-border/80 bg-card shadow-2xl shadow-foreground/[0.06]">
                        <div className="flex items-center gap-1.5 border-b border-border/60 bg-muted/40 px-4 py-2.5">
                            <div className="flex items-center gap-1.5">
                                <span className="size-2.5 rounded-full bg-border" />
                                <span className="size-2.5 rounded-full bg-border" />
                                <span className="size-2.5 rounded-full bg-border" />
                            </div>
                            <div className="ms-3 hidden items-center gap-1.5 rounded-md border border-border/60 bg-background px-2.5 py-1 font-mono text-[11px] text-muted-foreground sm:flex">
                                <Lock className="size-3" />
                                store.yourbrand.com
                            </div>
                        </div>

                        <div className="grid gap-0 sm:grid-cols-[200px_1fr]">
                            <aside className="hidden flex-col gap-1 border-e border-border/60 bg-muted/20 p-4 text-sm sm:flex">
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
                                            item.active
                                                ? 'bg-background text-foreground shadow-sm'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <item.icon className="size-3.5" />
                                        {item.label}
                                    </div>
                                ))}
                            </aside>

                            <div className="p-5 sm:p-6">
                                <div className="mb-5 flex items-end justify-between">
                                    <div>
                                        <p className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                                            {t('hero.preview_revenue')}
                                        </p>
                                        <p className="mt-1 font-display text-2xl font-semibold tabular-nums sm:text-3xl">
                                            $48,392<span className="ms-1 text-base font-normal text-muted-foreground">.21</span>
                                        </p>
                                    </div>
                                    <div className="hidden items-center gap-1 rounded-full border border-border/80 px-2 py-0.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400 sm:inline-flex">
                                        ↑ 23.4%
                                    </div>
                                </div>

                                <div className="mb-5 grid h-24 grid-cols-12 items-end gap-1.5 sm:h-28">
                                    {[40, 65, 50, 80, 55, 90, 70, 95, 60, 85, 75, 100].map((h, i) => (
                                        <div
                                            key={i}
                                            className="rounded-sm bg-gradient-to-t from-primary/70 to-primary transition-all duration-500 hover:from-primary hover:to-fuchsia-500"
                                            style={{ height: `${h}%` }}
                                        />
                                    ))}
                                </div>

                                <div className="rounded-lg border border-border/60">
                                    {[
                                        { id: '#ORD-2841', name: 'Stripe Toolkit Pro', amount: '$129.00', status: 'Paid' },
                                        { id: '#ORD-2840', name: 'CRM Boilerplate', amount: '$49.00', status: 'Paid' },
                                        { id: '#ORD-2839', name: 'API Credits · 5k', amount: '$199.00', status: 'Trial' },
                                    ].map((order, i, arr) => (
                                        <div
                                            key={order.id}
                                            className={cn(
                                                'grid grid-cols-[auto_1fr_auto_auto] items-center gap-3 px-4 py-2.5 text-[13px]',
                                                i < arr.length - 1 && 'border-b border-border/60',
                                            )}
                                        >
                                            <span className="font-mono text-[11px] text-muted-foreground">{order.id}</span>
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
                                                {order.status}
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

function LogoCloud() {
    const { t } = useTranslate();
    const logos = ['LARAVEL', 'STRIPE', 'INERTIA', 'TAILWIND', 'PADDLE', 'CLOUDFLARE'];
    return (
        <section className="border-y border-border/60 bg-muted/20 py-12">
            <Container>
                <p className="mb-8 text-center text-xs font-medium uppercase tracking-widest text-muted-foreground">
                    {t('logo_cloud.tagline')}
                </p>
                <div className="grid grid-cols-2 items-center justify-items-center gap-x-12 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                    {logos.map((logo) => (
                        <span
                            key={logo}
                            className="font-display text-base font-semibold tracking-[0.2em] text-muted-foreground/70 transition-colors hover:text-foreground"
                        >
                            {logo}
                        </span>
                    ))}
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
                    eyebrow={<><Zap className="size-3" /> {t('features.eyebrow')}</>}
                    title={t('features.title')}
                    description={t('features.description')}
                />

                <div className="mt-16 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-border/60 bg-border/60 sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURE_ICONS.map(({ key, icon: Icon }) => (
                        <div
                            key={key}
                            className="group relative bg-card p-6 transition-colors duration-200 hover:bg-muted/40 sm:p-8"
                        >
                            <div className="mb-5 inline-flex size-10 items-center justify-center rounded-lg border border-border/80 bg-background text-foreground transition-colors duration-200 group-hover:border-foreground/30">
                                <Icon className="size-4" />
                            </div>
                            <h3 className="mb-2 font-display text-base font-semibold tracking-tight">
                                {t(`features.items.${key}.title`)}
                            </h3>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {t(`features.items.${key}.body`)}
                            </p>
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
        <Section className="border-t border-border/60 bg-muted/20">
            <Container>
                <SectionHeading
                    eyebrow={<><Layers className="size-3" /> {t('product_types.eyebrow')}</>}
                    title={t('product_types.title')}
                    description={t('product_types.description')}
                />

                <div className="mt-16 grid gap-4 sm:grid-cols-2">
                    {PRODUCT_TYPE_ICONS.map(({ key, icon: Icon }) => (
                        <div
                            key={key}
                            className="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-6 transition-shadow hover:shadow-lg hover:shadow-foreground/[0.04] sm:p-7"
                        >
                            <div
                                aria-hidden
                                className="absolute -end-12 -top-12 size-40 rounded-full bg-foreground/[0.02] blur-2xl transition-all group-hover:scale-110 dark:bg-foreground/[0.06]"
                            />
                            <div className="relative flex items-start gap-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-background">
                                    <Icon className="size-4" />
                                </div>
                                <div className="space-y-1">
                                    <h3 className="font-display text-base font-semibold tracking-tight">
                                        {t(`product_types.items.${key}.label`)}
                                    </h3>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {t(`product_types.items.${key}.desc`)}
                                    </p>
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
                    eyebrow={<><Cloud className="size-3" /> {t('pricing.eyebrow')}</>}
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
                                    'relative flex flex-col rounded-2xl border bg-card p-6 sm:p-8',
                                    featured
                                        ? 'border-foreground/40 shadow-xl shadow-foreground/[0.06] lg:-mt-4 lg:mb-0'
                                        : 'border-border/60',
                                )}
                            >
                                {featured && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary to-fuchsia-500 px-3 py-1 font-mono text-[10px] uppercase tracking-wider text-white shadow-lg shadow-primary/30">
                                        {t('pricing.most_popular')}
                                    </div>
                                )}

                                <div className="mb-6">
                                    <h3 className="font-display text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                        {plan?.name}
                                    </h3>
                                    <div className="mt-3 flex items-baseline gap-1">
                                        <span className="font-display text-4xl font-semibold tracking-tight">{plan?.price}</span>
                                        <span className="text-sm text-muted-foreground">{plan?.cadence}</span>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">{plan?.description}</p>
                                </div>

                                <ul className="mb-8 flex-1 space-y-3 text-sm">
                                    {features.map((feat) => (
                                        <li key={feat} className="flex items-start gap-2.5">
                                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                                            <span>{feat}</span>
                                        </li>
                                    ))}
                                </ul>

                                <Button
                                    asChild
                                    size="lg"
                                    variant={featured ? 'default' : 'outline'}
                                    className="w-full"
                                >
                                    <Link href={route('register')}>{plan?.cta}</Link>
                                </Button>
                            </div>
                        );
                    })}
                </div>

                <p className="mt-8 text-center text-xs text-muted-foreground">{t('pricing.footnote')}</p>
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
        <Section id="testimonials" className="border-t border-border/60">
            <Container>
                <SectionHeading
                    eyebrow={t('testimonials.eyebrow')}
                    title={t('testimonials.title')}
                    description={t('testimonials.description')}
                />

                <div className="mt-16 grid gap-4 md:grid-cols-3">
                    {items.map((tm, i) => (
                        <figure
                            key={i}
                            className="flex flex-col gap-6 rounded-xl border border-border/60 bg-card p-6 sm:p-7"
                        >
                            <div aria-hidden className="font-display text-3xl leading-none text-foreground/15">
                                “
                            </div>
                            <blockquote className="text-pretty flex-1 text-[15px] leading-relaxed text-foreground/90">
                                {tm.quote}
                            </blockquote>
                            <figcaption className="flex items-center gap-3 border-t border-border/60 pt-4">
                                <div className="flex size-9 items-center justify-center rounded-full bg-foreground text-xs font-semibold text-background">
                                    {tm.name
                                        .split(' ')
                                        .map((n) => n[0])
                                        .join('')
                                        .slice(0, 2)}
                                </div>
                                <div className="text-sm">
                                    <div className="font-medium">{tm.name}</div>
                                    <div className="text-xs text-muted-foreground">{tm.role}</div>
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
    q: string;
    a: string;
}

function FAQ() {
    const { t, tList } = useTranslate();
    const faqs = tList<FAQShape[]>('faq.items') ?? [];
    const [open, setOpen] = useState<number | null>(0);
    return (
        <Section id="faq" className="border-t border-border/60 bg-muted/20">
            <Container>
                <SectionHeading
                    eyebrow={t('faq.eyebrow')}
                    title={t('faq.title')}
                    description={t('faq.description')}
                />

                <div className="mx-auto mt-16 max-w-3xl divide-y divide-border/60 overflow-hidden rounded-xl border border-border/60 bg-card">
                    {faqs.map((faq, i) => {
                        const isOpen = open === i;
                        return (
                            <div key={i}>
                                <button
                                    type="button"
                                    onClick={() => setOpen(isOpen ? null : i)}
                                    className="flex w-full items-center justify-between gap-4 px-5 py-4 text-start text-sm transition-colors hover:bg-muted/40 sm:px-6 sm:py-5"
                                    aria-expanded={isOpen}
                                >
                                    <span className="font-medium">{faq.q}</span>
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full border border-border/80 text-muted-foreground">
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
                                        <p className="px-5 pb-5 text-sm leading-relaxed text-muted-foreground sm:px-6 sm:pb-6">
                                            {faq.a}
                                        </p>
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
        <Section className="border-t border-border/60 pb-24 pt-20 sm:pb-32">
            <Container>
                <div className="relative isolate overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-10 text-white shadow-2xl shadow-primary/30 sm:p-16 dark:from-indigo-500 dark:via-violet-500 dark:to-fuchsia-500">
                    <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                        <div className="absolute inset-0 bg-grid opacity-[0.18]" />
                        <div className="absolute -end-32 -top-32 size-72 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-24 -start-20 size-64 rounded-full bg-fuchsia-300/30 blur-3xl" />
                    </div>
                    <div className="mx-auto flex max-w-xl flex-col items-center gap-5 text-center">
                        <h2 className="text-balance font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            {t('cta_strip.title')}
                        </h2>
                        <p className="text-pretty text-base text-white/85">{t('cta_strip.body')}</p>
                        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                            <Button
                                asChild
                                size="lg"
                                className="border-transparent bg-white text-indigo-700 shadow-md hover:bg-white/90"
                            >
                                <Link href={authed ? route('dashboard') : route('register')}>
                                    {authed ? t('common.go_to_dashboard') : t('cta_strip.primary')}
                                    <span aria-hidden>{arrow}</span>
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="ghost"
                                className="text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link href={route('products.index')}>{t('cta_strip.secondary')}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </Container>
        </Section>
    );
}
