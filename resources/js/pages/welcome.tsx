import { Button } from '@/components/ui/button';
import { Container, Eyebrow, Section, SectionHeading } from '@/components/ui/container';
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
    return (
        <Section className="overflow-hidden pt-20 sm:pt-28 lg:pt-32 pb-16 sm:pb-20">
            {/* Backdrop — radial spotlight + dotted grid */}
            <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                <div className="absolute inset-x-0 top-0 h-[600px] bg-spotlight" />
                <div className="absolute inset-0 bg-grid opacity-[0.35] mask-fade-b" />
            </div>

            <Container>
                <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
                    <Eyebrow>
                        <Sparkles className="size-3" />
                        <span>Now in public beta</span>
                        <span className="text-foreground/70">·</span>
                        <span className="font-sans normal-case tracking-normal text-foreground/80">v1.0 ships today</span>
                    </Eyebrow>

                    <h1 className="text-balance font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-[64px]">
                        The marketplace built for{' '}
                        <span className="relative whitespace-nowrap">
                            <span className="relative z-10 bg-gradient-to-r from-primary via-fuchsia-500 to-rose-500 bg-clip-text text-transparent">
                                makers who ship.
                            </span>
                            <span
                                aria-hidden
                                className="absolute inset-x-0 bottom-1 -z-0 h-[10px] bg-primary/10 dark:bg-primary/15"
                            />
                        </span>
                    </h1>

                    <p className="text-pretty max-w-2xl text-base text-muted-foreground sm:text-lg">
                        Sell Laravel scripts, SaaS products, APIs, templates, and licenses from one polished storefront.
                        Secure delivery, license keys, subscriptions, and payouts — built in.
                    </p>

                    <div className="mt-2 flex flex-col items-center gap-3 sm:flex-row">
                        <Button asChild size="lg">
                            <Link href={authed ? route('dashboard') : route('register')}>
                                {authed ? 'Open dashboard' : 'Start selling free'}
                                <ArrowRight />
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href={route('products.index')}>Browse marketplace</Link>
                        </Button>
                    </div>

                    <ul className="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-muted-foreground">
                        {[
                            'No credit card required',
                            'Stripe & Paddle ready',
                            '99.99% uptime',
                        ].map((item) => (
                            <li key={item} className="inline-flex items-center gap-1.5">
                                <CheckCircle2 className="size-3.5 text-foreground/70" />
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Product preview card — a stylized "app shell" */}
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
                            <div className="ml-3 hidden items-center gap-1.5 rounded-md border border-border/60 bg-background px-2.5 py-1 font-mono text-[11px] text-muted-foreground sm:flex">
                                <Lock className="size-3" />
                                store.yourbrand.com
                            </div>
                        </div>

                        <div className="grid gap-0 sm:grid-cols-[200px_1fr]">
                            <aside className="hidden flex-col gap-1 border-r border-border/60 bg-muted/20 p-4 text-sm sm:flex">
                                {[
                                    { icon: BarChart3, label: 'Overview', active: true },
                                    { icon: Layers, label: 'Products' },
                                    { icon: KeyRound, label: 'Licenses' },
                                    { icon: CreditCard, label: 'Orders' },
                                    { icon: Workflow, label: 'Subscriptions' },
                                    { icon: ShieldCheck, label: 'Settings' },
                                ].map((item) => (
                                    <div
                                        key={item.label}
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
                                            Revenue this month
                                        </p>
                                        <p className="mt-1 font-display text-2xl font-semibold tabular-nums sm:text-3xl">
                                            $48,392<span className="ml-1 text-base font-normal text-muted-foreground">.21</span>
                                        </p>
                                    </div>
                                    <div className="hidden items-center gap-1 rounded-full border border-border/80 px-2 py-0.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400 sm:inline-flex">
                                        ↑ 23.4%
                                    </div>
                                </div>

                                {/* Bar chart */}
                                <div className="mb-5 grid h-24 grid-cols-12 items-end gap-1.5 sm:h-28">
                                    {[40, 65, 50, 80, 55, 90, 70, 95, 60, 85, 75, 100].map((h, i) => (
                                        <div
                                            key={i}
                                            className="rounded-sm bg-gradient-to-t from-primary/70 to-primary transition-all duration-500 hover:from-primary hover:to-fuchsia-500"
                                            style={{ height: `${h}%` }}
                                        />
                                    ))}
                                </div>

                                {/* Mini orders list */}
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
    const logos = ['LARAVEL', 'STRIPE', 'INERTIA', 'TAILWIND', 'PADDLE', 'CLOUDFLARE'];
    return (
        <section className="border-y border-border/60 bg-muted/20 py-12">
            <Container>
                <p className="mb-8 text-center text-xs font-medium uppercase tracking-widest text-muted-foreground">
                    Trusted by makers shipping on
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

const FEATURES = [
    {
        icon: KeyRound,
        title: 'License management',
        body: 'Generate, activate, and revoke license keys with per-product activation limits, domain binding, and expiration windows.',
    },
    {
        icon: FileDown,
        title: 'Secure downloads',
        body: 'Signed URLs, download caps, and S3-compatible storage with per-customer audit trail. No leaked files.',
    },
    {
        icon: CreditCard,
        title: 'Subscriptions & one-time',
        body: 'Stripe and Paddle ready. Mix lifetime, subscription, and API-credit billing on the same storefront.',
    },
    {
        icon: BarChart3,
        title: 'Real-time analytics',
        body: 'Revenue, churn, MRR, refunds, top products. Filters down to the cohort. Exports to CSV.',
    },
    {
        icon: ShieldCheck,
        title: 'Built-in security',
        body: 'Rate limiting, signed URLs, 2FA for admins, and CSRF — Laravel best practices, on by default.',
    },
    {
        icon: Globe2,
        title: 'SEO out of the box',
        body: 'Per-product meta tags, sitemaps, Open Graph, structured data. Rank without plugins.',
    },
];

function FeatureGrid() {
    return (
        <Section>
            <Container>
                <SectionHeading
                    eyebrow={
                        <>
                            <Zap className="size-3" /> Everything you need
                        </>
                    }
                    title="A complete storefront — without the spreadsheet"
                    description="Skip the cobbled-together SaaS. Manage products, licenses, subscriptions, payouts, and customers from one polished admin."
                />

                <div className="mt-16 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-border/60 bg-border/60 sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURES.map((feature) => (
                        <div
                            key={feature.title}
                            className="group relative bg-card p-6 transition-colors duration-200 hover:bg-muted/40 sm:p-8"
                        >
                            <div className="mb-5 inline-flex size-10 items-center justify-center rounded-lg border border-border/80 bg-background text-foreground transition-colors duration-200 group-hover:border-foreground/30">
                                <feature.icon className="size-4" />
                            </div>
                            <h3 className="mb-2 font-display text-base font-semibold tracking-tight">{feature.title}</h3>
                            <p className="text-sm leading-relaxed text-muted-foreground">{feature.body}</p>
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

const PRODUCT_TYPES = [
    {
        icon: FileDown,
        label: 'Digital downloads',
        desc: 'ZIPs, PDFs, source code. Signed URLs, version control, customer download history.',
    },
    {
        icon: CreditCard,
        label: 'Subscriptions',
        desc: 'Monthly and annual plans with trials, dunning, proration, and Stripe-billed invoices.',
    },
    {
        icon: Code2,
        label: 'API access',
        desc: 'Issue API keys, rate-limit by plan, and bill credits or usage. Webhook events out of the box.',
    },
    {
        icon: Fingerprint,
        label: 'License keys',
        desc: 'Single-site, unlimited, or developer licenses. Activate from your script with one request.',
    },
];

function ProductTypes() {
    return (
        <Section className="border-t border-border/60 bg-muted/20">
            <Container>
                <SectionHeading
                    eyebrow={
                        <>
                            <Layers className="size-3" /> Sell anything digital
                        </>
                    }
                    title="One platform. Every product type."
                    description="Mix and match. Sell a script as a one-time download, the API as a subscription, and the source code as a developer license — all from the same dashboard."
                />

                <div className="mt-16 grid gap-4 sm:grid-cols-2">
                    {PRODUCT_TYPES.map((type) => (
                        <div
                            key={type.label}
                            className="group relative overflow-hidden rounded-xl border border-border/60 bg-card p-6 transition-shadow hover:shadow-lg hover:shadow-foreground/[0.04] sm:p-7"
                        >
                            <div
                                aria-hidden
                                className="absolute -right-12 -top-12 size-40 rounded-full bg-foreground/[0.02] blur-2xl transition-all group-hover:scale-110 dark:bg-foreground/[0.06]"
                            />
                            <div className="relative flex items-start gap-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-background">
                                    <type.icon className="size-4" />
                                </div>
                                <div className="space-y-1">
                                    <h3 className="font-display text-base font-semibold tracking-tight">{type.label}</h3>
                                    <p className="text-sm leading-relaxed text-muted-foreground">{type.desc}</p>
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

const PLANS = [
    {
        name: 'Starter',
        price: '$0',
        cadence: '/forever',
        description: 'Get your storefront live this weekend.',
        cta: 'Start free',
        featured: false,
        features: [
            'Up to 10 products',
            'Stripe checkout',
            '1 GB secure storage',
            'Email support',
        ],
    },
    {
        name: 'Pro',
        price: '$29',
        cadence: '/month',
        description: 'Everything you need to scale to your first 1,000 customers.',
        cta: 'Start 14-day trial',
        featured: true,
        features: [
            'Unlimited products',
            'License keys & subscriptions',
            '100 GB secure storage',
            'Custom domain',
            'Analytics & exports',
            'Priority support',
        ],
    },
    {
        name: 'Scale',
        price: 'Custom',
        cadence: '',
        description: 'For teams shipping at volume with SLAs.',
        cta: 'Talk to sales',
        featured: false,
        features: [
            'Everything in Pro',
            'Dedicated S3 region',
            'SAML SSO + audit logs',
            'Custom invoicing',
            '99.99% SLA',
        ],
    },
];

function Pricing() {
    return (
        <Section id="pricing">
            <Container>
                <SectionHeading
                    eyebrow={
                        <>
                            <Cloud className="size-3" /> Simple pricing
                        </>
                    }
                    title="Pay for growth, not for software."
                    description="Start free, then upgrade when you hit your first dollar. No per-product or per-seat surprises."
                />

                <div className="mt-16 grid gap-4 lg:grid-cols-3">
                    {PLANS.map((plan) => (
                        <div
                            key={plan.name}
                            className={cn(
                                'relative flex flex-col rounded-2xl border bg-card p-6 sm:p-8',
                                plan.featured
                                    ? 'border-foreground/40 shadow-xl shadow-foreground/[0.06] lg:-mt-4 lg:mb-0'
                                    : 'border-border/60',
                            )}
                        >
                            {plan.featured && (
                                <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary to-fuchsia-500 px-3 py-1 font-mono text-[10px] uppercase tracking-wider text-white shadow-lg shadow-primary/30">
                                    Most popular
                                </div>
                            )}

                            <div className="mb-6">
                                <h3 className="font-display text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                    {plan.name}
                                </h3>
                                <div className="mt-3 flex items-baseline gap-1">
                                    <span className="font-display text-4xl font-semibold tracking-tight">{plan.price}</span>
                                    <span className="text-sm text-muted-foreground">{plan.cadence}</span>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">{plan.description}</p>
                            </div>

                            <ul className="mb-8 flex-1 space-y-3 text-sm">
                                {plan.features.map((feat) => (
                                    <li key={feat} className="flex items-start gap-2.5">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <span>{feat}</span>
                                    </li>
                                ))}
                            </ul>

                            <Button
                                asChild
                                size="lg"
                                variant={plan.featured ? 'default' : 'outline'}
                                className="w-full"
                            >
                                <Link href={route('register')}>{plan.cta}</Link>
                            </Button>
                        </div>
                    ))}
                </div>

                <p className="mt-8 text-center text-xs text-muted-foreground">
                    Plus Stripe/Paddle fees. No platform commission on Pro and Scale.
                </p>
            </Container>
        </Section>
    );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  TESTIMONIALS                                                              */
/* ────────────────────────────────────────────────────────────────────────── */

const TESTIMONIALS = [
    {
        quote:
            'We migrated three Gumroad products to StoreProject in a weekend. License activation that used to be a Google Sheet is now a single API call.',
        name: 'Maya Rodriguez',
        role: 'Founder, Stripeline',
    },
    {
        quote:
            'The admin is exactly what I’d build if I had three more months. We replaced a Notion + Zapier setup and finally have real analytics.',
        name: 'Jonas Vetter',
        role: 'CTO, Sailwave',
    },
    {
        quote:
            'Subscriptions, downloads, and API credits in one place. The hairline-perfect UI doesn’t hurt either.',
        name: 'Aiko Tanaka',
        role: 'Indie dev, ToolBelt',
    },
];

function Testimonials() {
    return (
        <Section id="testimonials" className="border-t border-border/60">
            <Container>
                <SectionHeading
                    eyebrow="Loved by builders"
                    title="Stories from the shipping room"
                    description="Teams using StoreProject to sell scripts, APIs, and SaaS products to customers around the world."
                />

                <div className="mt-16 grid gap-4 md:grid-cols-3">
                    {TESTIMONIALS.map((t, i) => (
                        <figure
                            key={i}
                            className="flex flex-col gap-6 rounded-xl border border-border/60 bg-card p-6 sm:p-7"
                        >
                            <div aria-hidden className="font-display text-3xl leading-none text-foreground/15">
                                “
                            </div>
                            <blockquote className="text-pretty flex-1 text-[15px] leading-relaxed text-foreground/90">
                                {t.quote}
                            </blockquote>
                            <figcaption className="flex items-center gap-3 border-t border-border/60 pt-4">
                                <div className="flex size-9 items-center justify-center rounded-full bg-foreground text-xs font-semibold text-background">
                                    {t.name
                                        .split(' ')
                                        .map((n) => n[0])
                                        .join('')}
                                </div>
                                <div className="text-sm">
                                    <div className="font-medium">{t.name}</div>
                                    <div className="text-xs text-muted-foreground">{t.role}</div>
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

const FAQS = [
    {
        q: 'Can I migrate from Gumroad / Lemonsqueezy?',
        a: 'Yes. Import your products and customer list via CSV; license keys can be backfilled with the migration command. We also keep your old slugs alive with permanent redirects.',
    },
    {
        q: 'Do I own my customer data?',
        a: 'Always. Every customer record, license, and download log lives in your database. Export at any time as CSV or JSON.',
    },
    {
        q: 'Which payment providers do you support?',
        a: 'Stripe and Paddle are first-class. Add additional gateways via the Payments service layer — it ships as a clean interface.',
    },
    {
        q: 'How does license validation work?',
        a: 'POST your key to the /licenses/validate endpoint. We return activation state, expiration, and bound domain. Plug it into your script in under five minutes.',
    },
    {
        q: 'Is there a self-hosted version?',
        a: 'StoreProject is open-core. Self-host the full stack on any VPS, or use our managed hosting on the Pro and Scale plans.',
    },
];

function FAQ() {
    const [open, setOpen] = useState<number | null>(0);
    return (
        <Section id="faq" className="border-t border-border/60 bg-muted/20">
            <Container>
                <SectionHeading
                    eyebrow="Frequently asked"
                    title="Answers, no marketing fluff."
                    description="If you don’t find what you need, the team replies in under an hour during business days."
                />

                <div className="mx-auto mt-16 max-w-3xl divide-y divide-border/60 overflow-hidden rounded-xl border border-border/60 bg-card">
                    {FAQS.map((faq, i) => {
                        const isOpen = open === i;
                        return (
                            <div key={faq.q}>
                                <button
                                    type="button"
                                    onClick={() => setOpen(isOpen ? null : i)}
                                    className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm transition-colors hover:bg-muted/40 sm:px-6 sm:py-5"
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
    return (
        <Section className="border-t border-border/60 pb-24 pt-20 sm:pb-32">
            <Container>
                <div className="relative isolate overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-10 text-white shadow-2xl shadow-primary/30 sm:p-16 dark:from-indigo-500 dark:via-violet-500 dark:to-fuchsia-500">
                    <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                        <div className="absolute inset-0 bg-grid opacity-[0.18]" />
                        <div className="absolute -right-32 -top-32 size-72 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-24 -left-20 size-64 rounded-full bg-fuchsia-300/30 blur-3xl" />
                    </div>
                    <div className="mx-auto flex max-w-xl flex-col items-center gap-5 text-center">
                        <h2 className="text-balance font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            Ship your storefront this weekend.
                        </h2>
                        <p className="text-pretty text-base text-white/85">
                            Spin up a fully-featured marketplace in minutes. The hard parts — licenses, downloads, billing —
                            are already done.
                        </p>
                        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                            <Button
                                asChild
                                size="lg"
                                className="border-transparent bg-white text-indigo-700 shadow-md hover:bg-white/90"
                            >
                                <Link href={authed ? route('dashboard') : route('register')}>
                                    {authed ? 'Go to dashboard' : 'Start selling free'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="ghost"
                                className="text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link href={route('products.index')}>See live storefront</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </Container>
        </Section>
    );
}
