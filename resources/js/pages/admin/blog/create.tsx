import AppLayout from '@/layouts/app-layout';
import BlogPostForm, { type BlogPostFormValues } from '@/pages/admin/blog/blog-post-form';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Option {
    value: string;
    label: string;
}

interface AdminBlogCreateProps {
    statuses: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Blog', href: '/admin/blog-posts' },
    { title: 'New', href: '/admin/blog-posts/create' },
];

export default function AdminBlogCreate({ statuses }: AdminBlogCreateProps) {
    const { data, setData, post, processing, errors } = useForm<BlogPostFormValues>({
        title: '',
        slug: '',
        excerpt: '',
        content: '',
        thumbnail: '',
        status: 'draft',
        published_at: '',
        tags: '',
        seo_title: '',
        seo_description: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.blog-posts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New post" />

            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">New post</h1>
                    <p className="text-muted-foreground text-sm">Write a post for the public blog.</p>
                </div>

                <BlogPostForm
                    data={data}
                    setData={setData}
                    errors={errors as Partial<Record<keyof BlogPostFormValues, string>>}
                    processing={processing}
                    submitLabel="Create post"
                    onSubmit={submit}
                    statuses={statuses}
                    cancelHref={route('admin.blog-posts.index')}
                />
            </div>
        </AppLayout>
    );
}
