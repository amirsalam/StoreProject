import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/hooks/use-translate';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

export type LocalizedText = Record<string, string | null>;

export interface Faq {
    id: number;
    question: LocalizedText;
    answer: LocalizedText;
    sort_order: number;
    is_active: boolean;
}

export interface FaqFormData {
    question: Record<string, string>;
    answer: Record<string, string>;
    is_active: boolean;
    [key: string]: Record<string, string> | boolean;
}

/** Language names in their own language — the same in every dashboard locale. */
export const LANGUAGE_NAMES: Record<string, string> = {
    en: 'English',
    ar: 'العربية',
    fr: 'Français',
    es: 'Español',
};

const RTL = ['ar'];

export function emptyTexts(locales: string[], from?: LocalizedText): Record<string, string> {
    return Object.fromEntries(locales.map((l) => [l, from?.[l] ?? '']));
}

interface Props {
    locales: string[];
    data: FaqFormData;
    setData: (key: 'question' | 'answer' | 'is_active', value: Record<string, string> | boolean) => void;
    errors: Record<string, string | undefined>;
    processing: boolean;
    onSubmit: (e: FormEvent) => void;
    submitLabel: string;
}

/**
 * Shared create / edit form for a homepage FAQ entry: one tab per site
 * language. English is required; empty languages show the English text.
 */
export default function FaqForm({ locales, data, setData, errors, processing, onSubmit, submitLabel }: Props) {
    const { __ } = useTranslate();
    const [tab, setTab] = useState(locales[0] ?? 'en');

    const hasError = (l: string) => Boolean(errors[`question.${l}`] || errors[`answer.${l}`]);
    const isFilled = (l: string) => Boolean(data.question[l]?.trim() && data.answer[l]?.trim());

    const submit = (e: FormEvent) => {
        // Jump to the first language with a problem so the message is visible.
        const firstMissing = !data.question.en?.trim() || !data.answer.en?.trim() ? 'en' : null;
        if (firstMissing) setTab(firstMissing);
        onSubmit(e);
    };

    const dir = RTL.includes(tab) ? 'rtl' : 'ltr';

    return (
        <form onSubmit={submit} className="space-y-6" noValidate>
            <section className="border-border bg-card space-y-5 rounded-xl border p-6 shadow-sm sm:p-8">
                <div role="tablist" className="bg-muted/50 inline-flex flex-wrap gap-1 rounded-lg p-1">
                    {locales.map((l) => (
                        <button
                            key={l}
                            type="button"
                            role="tab"
                            aria-selected={tab === l}
                            onClick={() => setTab(l)}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors',
                                tab === l ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {LANGUAGE_NAMES[l] ?? l.toUpperCase()}
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    hasError(l) ? 'bg-destructive' : isFilled(l) ? 'bg-emerald-500' : 'bg-muted-foreground/30',
                                )}
                                aria-hidden
                            />
                        </button>
                    ))}
                </div>

                <p className="text-muted-foreground text-xs">
                    {tab === 'en'
                        ? __('English is required. It is shown for every language you leave empty.')
                        : __('Optional. Leave empty to show the English text in this language.')}
                </p>

                <div className="space-y-1.5">
                    <Label htmlFor={`question-${tab}`}>{__('Question')}</Label>
                    <Input
                        id={`question-${tab}`}
                        dir={dir}
                        value={data.question[tab] ?? ''}
                        onChange={(e) => setData('question', { ...data.question, [tab]: e.target.value })}
                        maxLength={255}
                        placeholder={tab === 'en' ? '' : (data.question.en ?? '')}
                        autoComplete="off"
                    />
                    <InputError message={errors[`question.${tab}`]} />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor={`answer-${tab}`}>{__('Answer')}</Label>
                    <textarea
                        id={`answer-${tab}`}
                        dir={dir}
                        value={data.answer[tab] ?? ''}
                        onChange={(e) => setData('answer', { ...data.answer, [tab]: e.target.value })}
                        maxLength={5000}
                        rows={6}
                        placeholder={tab === 'en' ? '' : (data.answer.en ?? '')}
                        className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-hidden"
                    />
                    <InputError message={errors[`answer.${tab}`]} />
                </div>

                <label className="flex items-center gap-3 text-sm">
                    <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                    <span>
                        <span className="font-medium">{__('Show on the homepage')}</span>
                        <span className="text-muted-foreground block text-xs">{__('Hidden questions stay saved but are not displayed.')}</span>
                    </span>
                </label>
            </section>

            <div className="flex flex-wrap items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={route('admin.faqs.index')}>{__('Cancel')}</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Loader2 className="animate-spin" />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
