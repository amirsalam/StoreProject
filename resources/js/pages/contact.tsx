import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Container, Eyebrow, Section } from '@/components/ui/container';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { FormEvent } from 'react';

interface ContactFormValues {
    name: string;
    email: string;
    subject: string;
    message: string;
    /** Honeypot — hidden from people, so anything here means a bot. */
    website: string;
    [key: string]: string;
}

const TEXTAREA_CLASS =
    'flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring';

export default function Contact() {
    const { auth, branding, flash } = usePage<SharedData & { flash: { success: string | null } }>().props;
    const { t } = useTranslate();
    const brandTitle = branding?.title ?? 'StoreProject';

    const { data, setData, post, processing, errors, reset } = useForm<ContactFormValues>({
        name: auth?.user?.name ?? '',
        email: auth?.user?.email ?? '',
        subject: '',
        message: '',
        website: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('contact.store'), {
            preserveScroll: true,
            onSuccess: () => reset('subject', 'message'),
        });
    };

    return (
        <StorefrontLayout>
            <Head title={`${t('contact.meta_title')} — ${brandTitle}`} />

            <Section className="overflow-hidden pt-20 pb-24 sm:pt-28 sm:pb-32">
                <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
                    <div className="bg-spotlight absolute inset-x-0 top-0 h-[420px]" />
                    <div className="bg-grid mask-fade-b absolute inset-0 opacity-[0.35]" />
                </div>

                <Container>
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-4 text-center">
                        <Eyebrow>{t('contact.eyebrow')}</Eyebrow>
                        <h1 className="font-display text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                            {t('contact.title')}
                        </h1>
                        <p className="text-muted-foreground text-pretty sm:text-lg">{t('contact.lead')}</p>
                    </div>

                    <div className="mx-auto mt-12 max-w-xl">
                        {flash?.success && (
                            <div
                                role="status"
                                className="mb-6 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
                            >
                                <CheckCircle2 className="size-4 shrink-0" />
                                {flash.success}
                            </div>
                        )}

                        <form onSubmit={submit} className="border-border/60 bg-card space-y-5 rounded-xl border p-6 sm:p-8">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="name">{t('contact.form.name')}</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        autoComplete="name"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="email">{t('contact.form.email')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        autoComplete="email"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="subject">{t('contact.form.subject')}</Label>
                                <Input id="subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} required />
                                <InputError message={errors.subject} />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="message">{t('contact.form.message')}</Label>
                                <textarea
                                    id="message"
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    rows={7}
                                    required
                                    minLength={10}
                                    maxLength={5000}
                                    className={TEXTAREA_CLASS}
                                />
                                <InputError message={errors.message} />
                            </div>

                            {/* Honeypot: off-screen and skipped by keyboard + screen readers. */}
                            <div aria-hidden className="sr-only">
                                <Label htmlFor="website">Website</Label>
                                <Input
                                    id="website"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    tabIndex={-1}
                                    autoComplete="off"
                                />
                            </div>
                            <InputError message={errors.website} />

                            <div className="flex items-center justify-between gap-4">
                                <p className="text-muted-foreground text-xs">{t('contact.form.privacy_note')}</p>
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('common.loading') : t('contact.form.submit')}
                                </Button>
                            </div>
                        </form>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
