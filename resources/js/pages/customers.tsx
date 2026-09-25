import { Button } from '@/components/ui/button';
import { Container, Eyebrow, Section, SectionHeading } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Code2, type LucideIcon, Repeat, Workflow } from 'lucide-react';

interface TestimonialShape {
    quote: string;
    name: string;
    role: string;
}

const USE_CASES: { key: string; icon: LucideIcon }[] = [
    { key: 'scripts', icon: Code2 },
    { key: 'api', icon: Workflow },
    { key: 'saas', icon: Repeat },
];

export default function Customers() {
    const { branding } = usePage<SharedData>().props;
    const { t, tList, direction } = useTranslate();
    const brandTitle = branding?.title ?? 'StoreProject';
    const arrow = direction === 'rtl' ? '←' : '→';
    const testimonials = tList<TestimonialShape[]>('testimonials.items') ?? [];

    return (
        <StorefrontLayout>
            <Head title={`${t('customers.meta_title')} — ${brandTitle}`} />

            {/* Hero */}
            <Section className="overflow-hidden pt-20 pb-12 sm:pt-28 sm:pb-16">
                <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                    <div className="bg-spotlight absolute inset-x-0 top-0 h-[500px]" />
                    <div className="bg-grid mask-fade-b absolute inset-0 opacity-[0.35]" />
                </div>
                <Container>
                    <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
                        <Eyebrow>{t('customers.eyebrow')}</Eyebrow>
                        <h1 className="font-display text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                            {t('customers.title')}
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-base text-pretty sm:text-lg">{t('customers.lead')}</p>
                    </div>
                </Container>
            </Section>

            {/* What they sell */}
            <Section className="border-border/60 border-t">
                <Container>
                    <SectionHeading title={t('customers.use_cases.title')} description={t('customers.use_cases.description')} />
                    <div className="mx-auto mt-12 grid max-w-5xl gap-4 md:grid-cols-3">
                        {USE_CASES.map(({ key, icon: Icon }) => (
                            <div key={key} className="border-border/60 bg-card rounded-xl border p-6">
                                <div className="bg-primary/10 text-primary mb-4 inline-flex size-10 items-center justify-center rounded-lg">
                                    <Icon className="size-5" />
                                </div>
                                <h3 className="font-semibold">{t(`customers.use_cases.items.${key}.title`)}</h3>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{t(`customers.use_cases.items.${key}.body`)}</p>
                            </div>
                        ))}
                    </div>
                </Container>
            </Section>

            {/* Testimonials — same copy as the home page section this page replaces. */}
            <Section id="testimonials" className="border-border/60 border-t">
                <Container>
                    <SectionHeading eyebrow={t('testimonials.eyebrow')} title={t('testimonials.title')} description={t('testimonials.description')} />

                    <div className="mt-16 grid gap-4 md:grid-cols-3">
                        {testimonials.map((tm, i) => (
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

            {/* CTA */}
            <Section className="border-border/60 border-t pb-24 sm:pb-32">
                <Container>
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-6 text-center">
                        <h2 className="font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{t('customers.cta.title')}</h2>
                        <p className="text-muted-foreground text-pretty">{t('customers.cta.body')}</p>
                        <div className="flex flex-col items-center gap-3 sm:flex-row">
                            <Button asChild size="lg">
                                <Link href={route('products.index')}>
                                    {t('customers.cta.browse')}
                                    <span aria-hidden>{arrow}</span>
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="outline">
                                <Link href={route('about')}>{t('customers.cta.about')}</Link>
                            </Button>
                        </div>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
