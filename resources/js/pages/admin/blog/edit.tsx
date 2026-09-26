import ConfirmDialog from '@/components/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import BlogPostForm, { type BlogPostFormValues } from '@/pages/admin/blog/blog-post-form';
import { type BlogPost, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface AdminBlogEditProps {
    post: BlogPost;
    statuses: Option[];
}

/** ISO timestamp -> the "YYYY-MM-DDTHH:mm" shape datetime-local expects. */
function toDateTimeLocal(value: string | null): string {
    return value ? value.slice(0, 16) : '';
}

export default function AdminBlogEdit({ post, statuses }: AdminBlogEditProps) {
    const { data, setData, put, processing, errors } = useForm<BlogPostFormValues>({
        title: post.title,
        slug: post.slug,
        excerpt: post.excerpt ?? '',
        content: post.content,
        thumbnail: post.thumbnail ?? '',
        status: post.status,
        published_at: toDateTimeLocal(post.published_at),
        tags: (post.tags ?? []).join(', '),
        seo_title: post.seo_title ?? '',
        seo_description: post.seo_description ?? '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin/products' },
        { title: 'Blog', href: '/admin/blog-posts' },
        { title: post.title, href: `/admin/blog-posts/${post.id}/edit` },
    ];

    // Saving asks for confirmation in a popup first.
    const [confirming, setConfirming] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setConfirming(true);
    };

    const confirmUpdate = () => {
        put(route('admin.blog-posts.update', post.id), {
            onFinish: () => setConfirming(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit · ${post.title}`} />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Edit post</h1>
                    <p className="text-muted-foreground text-sm">{post.title}</p>
                </div>

                <BlogPostForm
                    data={data}
                    setData={setData}
                    errors={errors as Partial<Record<keyof BlogPostFormValues, string>>}
                    processing={processing}
                    submitLabel="Save changes"
                    onSubmit={submit}
                    statuses={statuses}
                    cancelHref={route('admin.blog-posts.index')}
                />
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Save changes to this post?"
                description={
                    <>
                        The changes to <strong className="text-foreground">{post.title}</strong> are saved right away — if the post is published,
                        readers see them immediately.
                    </>
                }
                confirmLabel="Save changes"
                processing={processing}
                onConfirm={confirmUpdate}
            />
        </AppLayout>
    );
}
