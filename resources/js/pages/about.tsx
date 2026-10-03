import { Button } from '@/components/ui/button';
import { Container, Eyebrow, Section, SectionHeading } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { FileDown, Globe2, Heart, KeyRound, Layers, type LucideIcon, Repeat, ShieldCheck, Store, Workflow } from 'lucide-react';

const VALUES: { key: string; icon: LucideIcon }[] = [
    { key: 'makers', icon: Heart },
    { key: 'commerce', icon: ShieldCheck },
    { key: 'vendors', icon: Store },
    { key: 'global', icon: Globe2 },
];

const PRODUCT_TYPES: { key: string; icon: LucideIcon }[] = [
    { key: 'downloads', icon: FileDown },
    { key: 'licenses', icon: KeyRound },
    { key: 'api', icon: Workflow },
    { key: 'subscriptions', icon: Repeat },
];

export default function About() {
    const { auth, branding } = usePage<SharedData>().props;
    const { t, direction } = useTranslate();
    const brandTitle = branding?.title ?? 'StoreProject';
    const arrow = direction === 'rtl' ? '←' : '→';
    const authed = Boolean(auth?.user);

    return (
        <StorefrontLayout>
            <Head title={`${t('about.meta_title')} — ${brandTitle}`} />

            {/* Hero */}
            <Section className="overflow-hidden pt-20 pb-12 sm:pt-28 sm:pb-16">
                <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                    <div className="bg-spotlight absolute inset-x-0 top-0 h-[500px]" />
                    <div className="bg-grid mask-fade-b absolute inset-0 opacity-[0.35]" />
                </div>
                <Container>
                    <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
                        <Eyebrow>{t('about.eyebrow')}</Eyebrow>
                        <h1 className="font-display text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                            {t('about.title')}
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-base text-pretty sm:text-lg">{t('about.lead')}</p>
                    </div>
                </Container>
            </Section>

            {/* Mission */}
            <Section className="border-border/60 border-t">
                <Container>
                    <div className="mx-auto grid max-w-5xl gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16">
                        <h2 className="font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{t('about.mission.title')}</h2>
                        <div className="text-muted-foreground space-y-4 text-base leading-relaxed sm:text-lg">
                            <p>{t('about.mission.body_1')}</p>
                            <p>{t('about.mission.body_2')}</p>
                        </div>
                    </div>
                </Container>
            </Section>

            {/* Values */}
            <Section className="border-border/60 border-t">
                <Container>
                    <SectionHeading title={t('about.values.title')} description={t('about.values.description')} />
                    <div className="mx-auto mt-12 grid max-w-5xl gap-4 sm:grid-cols-2">
                        {VALUES.map(({ key, icon: Icon }) => (
                            <div key={key} className="border-border/60 bg-card rounded-xl border p-6">
                                <div className="bg-primary/10 text-primary mb-4 inline-flex size-10 items-center justify-center rounded-lg">
                                    <Icon className="size-5" />
                                </div>
                                <h3 className="font-semibold">{t(`about.values.items.${key}.title`)}</h3>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{t(`about.values.items.${key}.body`)}</p>
                            </div>
                        ))}
                    </div>
                </Container>
            </Section>

            {/* What you can sell */}
            <Section className="border-border/60 border-t">
                <Container>
                    <SectionHeading
                        eyebrow={
                            <>
                                <Layers className="size-3" /> {t('product_types.eyebrow')}
                            </>
                        }
                        title={t('product_types.title')}
                    />
                    <ul className="mx-auto mt-12 grid max-w-5xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {PRODUCT_TYPES.map(({ key, icon: Icon }) => (
                            <li key={key} className="border-border/60 rounded-xl border p-5">
                                <Icon className="text-foreground/70 size-5" />
                                <p className="mt-3 text-sm font-semibold">{t(`product_types.items.${key}.label`)}</p>
                                <p className="text-muted-foreground mt-1 text-sm">{t(`product_types.items.${key}.desc`)}</p>
                            </li>
                        ))}
                    </ul>
                </Container>
            </Section>

            {/* CTA */}
            <Section className="border-border/60 border-t pb-24 sm:pb-32">
                <Container>
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-6 text-center">
                        <h2 className="font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{t('about.cta.title')}</h2>
                        <p className="text-muted-foreground text-pretty">{t('about.cta.body')}</p>
                        <div className="flex flex-col items-center gap-3 sm:flex-row">
                            <Button asChild size="lg">
                                <Link href={route('products.index')}>
                                    {t('about.cta.browse')}
                                    <span aria-hidden>{arrow}</span>
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="outline">
                                <Link href={authed ? route('dashboard') : route('register')}>
                                    {authed ? t('common.open_dashboard') : t('about.cta.sell')}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
