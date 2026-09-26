import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Link } from '@inertiajs/react';
import { FormEvent } from 'react';

export interface BlogPostFormValues {
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    thumbnail: string;
    status: string;
    published_at: string;
    tags: string;
    seo_title: string;
    seo_description: string;
    [key: string]: string;
}

interface Option {
    value: string;
    label: string;
}

interface BlogPostFormProps {
    data: BlogPostFormValues;
    setData: <K extends keyof BlogPostFormValues>(key: K, value: BlogPostFormValues[K]) => void;
    errors: Partial<Record<keyof BlogPostFormValues, string>>;
    processing: boolean;
    submitLabel: string;
    onSubmit: (e: FormEvent) => void;
    statuses: Option[];
    cancelHref: string;
}

const TEXTAREA_CLASS =
    'flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring';

export default function BlogPostForm({ data, setData, errors, processing, submitLabel, onSubmit, statuses, cancelHref }: BlogPostFormProps) {
    return (
        <form onSubmit={onSubmit} className="space-y-8">
            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">Basics</h2>

                <Field label="Title" htmlFor="title" error={errors.title} required>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus />
                </Field>

                <Field label="Slug" htmlFor="slug" error={errors.slug} hint="URL-safe identifier. Auto-derived from title if left blank.">
                    <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                </Field>

                <Field label="Excerpt" htmlFor="excerpt" error={errors.excerpt} hint="Short summary shown on the blog index.">
                    <textarea
                        id="excerpt"
                        value={data.excerpt}
                        onChange={(e) => setData('excerpt', e.target.value)}
                        rows={3}
                        maxLength={500}
                        className={TEXTAREA_CLASS}
                    />
                </Field>

                <Field label="Content" htmlFor="content" error={errors.content} hint="Markdown. Raw HTML is stripped when rendered." required>
                    <textarea
                        id="content"
                        value={data.content}
                        onChange={(e) => setData('content', e.target.value)}
                        rows={18}
                        required
                        className={`${TEXTAREA_CLASS} font-mono`}
                    />
                </Field>
            </section>

            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">Publishing</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Status" htmlFor="status" error={errors.status} required>
                        <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                            <SelectTrigger id="status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        label="Publish date"
                        htmlFor="published_at"
                        error={errors.published_at}
                        hint="Leave blank to publish now. A future date keeps the post hidden until then."
                    >
                        <Input
                            id="published_at"
                            type="datetime-local"
                            value={data.published_at}
                            onChange={(e) => setData('published_at', e.target.value)}
                        />
                    </Field>
                </div>

                <Field label="Tags" htmlFor="tags" error={errors.tags} hint="Comma-separated, up to 10.">
                    <Input id="tags" value={data.tags} onChange={(e) => setData('tags', e.target.value)} placeholder="laravel, release" />
                </Field>

                <Field label="Thumbnail path" htmlFor="thumbnail" error={errors.thumbnail}>
                    <Input id="thumbnail" value={data.thumbnail} onChange={(e) => setData('thumbnail', e.target.value)} />
                </Field>
            </section>

            <section className="bg-card space-y-4 rounded-lg border p-6">
                <h2 className="text-base font-semibold">SEO</h2>
                <Field label="SEO title" htmlFor="seo_title" error={errors.seo_title}>
                    <Input id="seo_title" value={data.seo_title} onChange={(e) => setData('seo_title', e.target.value)} />
                </Field>
                <Field label="SEO description" htmlFor="seo_description" error={errors.seo_description}>
                    <textarea
                        id="seo_description"
                        value={data.seo_description}
                        onChange={(e) => setData('seo_description', e.target.value)}
                        rows={3}
                        maxLength={500}
                        className={TEXTAREA_CLASS}
                    />
                </Field>
            </section>

            <div className="flex items-center justify-end gap-2">
                <Button asChild variant="ghost">
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function Field({
    label,
    htmlFor,
    children,
    error,
    hint,
    required,
}: {
    label: string;
    htmlFor: string;
    children: React.ReactNode;
    error?: string;
    hint?: string;
    required?: boolean;
}) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
