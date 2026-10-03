import { Button } from '@/components/ui/button';
import { Container, Section } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface DraftSection {
    id: string;
    title: string;
    body: string;
}

/**
 * Shared shell for documents published as unreviewed outlines (the legal
 * pages, the docs and guides). Section bodies are drafting notes, so the
 * page always carries the draft warning and robots: noindex. When real
 * wording replaces the placeholder text, drop the banner and the noindex
 * in the same change.
 *
 * Translations live under `<ns>.<docKey>.*`, with the banner, status label
 * and table-of-contents heading shared per namespace (`<ns>.draft_banner`…).
 */
export default function DraftDocument({ ns, docKey }: { ns: 'legal' | 'help'; docKey: string }) {
    const { branding } = usePage<SharedData>().props;
    const { t, tList } = useTranslate();
    const brandTitle = branding?.title ?? 'StoreProject';
    const sections = tList<DraftSection[]>(`${ns}.${docKey}.sections`) ?? [];

    return (
        <StorefrontLayout>
            <Head title={`${t(`${ns}.${docKey}.meta_title`)} — ${brandTitle}`}>
                <meta name="robots" content="noindex" />
            </Head>

            <Section className="pt-16 pb-24 sm:pt-20 sm:pb-32">
                <Container>
                    <div className="mx-auto max-w-3xl">
                        <header className="flex flex-col gap-3">
                            <span className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">{t(`${ns}.status_label`)}</span>
                            <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                {t(`${ns}.${docKey}.title`)}
                            </h1>
                            <p className="text-muted-foreground text-pretty sm:text-lg">{t(`${ns}.${docKey}.lead`)}</p>
                        </header>

                        {/* Draft warning — deliberately loud, and first thing after the title. */}
                        <div
                            role="note"
                            className="mt-8 flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                            <div>
                                <p className="font-semibold">{t(`${ns}.draft_banner.title`)}</p>
                                <p className="mt-1 text-sm leading-relaxed">{t(`${ns}.draft_banner.body`)}</p>
                            </div>
                        </div>

                        {/* Table of contents */}
                        <nav aria-label={t(`${ns}.toc`)} className="border-border/60 mt-10 rounded-xl border p-5">
                            <h2 className="text-muted-foreground font-mono text-[11px] tracking-wider uppercase">{t(`${ns}.toc`)}</h2>
                            <ol className="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                                {sections.map((section, i) => (
                                    <li key={section.id} className="text-sm">
                                        <a href={`#${section.id}`} className="hover:text-foreground text-muted-foreground hover:underline">
                                            <span className="tabular-nums">{i + 1}.</span> {section.title}
                                        </a>
                                    </li>
                                ))}
                            </ol>
                        </nav>

                        <div className="mt-12 space-y-10">
                            {sections.map((section, i) => (
                                <section key={section.id} id={section.id} className="scroll-mt-24">
                                    <h2 className="font-display text-xl font-semibold tracking-tight sm:text-2xl">
                                        <span className="text-muted-foreground me-2 tabular-nums">{i + 1}.</span>
                                        {section.title}
                                    </h2>
                                    <p className="text-muted-foreground mt-3 leading-relaxed">{section.body}</p>
                                </section>
                            ))}
                        </div>

                        <div className="border-border/60 mt-16 flex flex-col items-start gap-3 border-t pt-8">
                            <h2 className="font-display text-xl font-semibold tracking-tight">{t(`${ns}.${docKey}.contact.title`)}</h2>
                            <p className="text-muted-foreground text-sm">{t(`${ns}.${docKey}.contact.body`)}</p>
                            <Button asChild variant="outline">
                                <Link href={route('contact')}>{t(`${ns}.${docKey}.contact.action`)}</Link>
                            </Button>
                        </div>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
