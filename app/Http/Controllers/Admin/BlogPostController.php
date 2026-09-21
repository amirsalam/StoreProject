<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Http\Requests\Admin\UpdateBlogPostRequest;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
        ];

        $query = BlogPost::query()->with('author:id,name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('slug', 'like', $term));
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('admin/blog/index', [
            'posts' => $query->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/blog/create', [
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $post = BlogPost::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', "Post \"{$post->title}\" created.");
    }

    public function edit(BlogPost $blogPost): Response
    {
        return Inertia::render('admin/blog/edit', [
            'post' => $blogPost,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blogPost): RedirectResponse
    {
        $blogPost->update($request->validated());

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', "Post \"{$blogPost->title}\" updated.");
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $title = $blogPost->title;
        $blogPost->delete();

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', "Post \"{$title}\" deleted.");
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => BlogPost::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => BlogPost::STATUS_PUBLISHED, 'label' => 'Published'],
        ];
    }
}
