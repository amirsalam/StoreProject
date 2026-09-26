<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    /**
     * Post body is authored as Markdown. It is rendered server-side with
     * raw HTML stripped, so a post can never inject markup into the page.
     */
    private const MARKDOWN_OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'tag' => (string) $request->string('tag'),
        ];

        $query = BlogPost::query()
            ->published()
            ->with('author:id,name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term);
            });
        }

        if ($filters['tag'] !== '') {
            // tags is a JSON column; whereJsonContains keeps the filter in SQL
            // rather than paginating in PHP.
            $query->whereJsonContains('tags', $filters['tag']);
        }

        $posts = $query->latest('published_at')->paginate(9)->withQueryString();

        return Inertia::render('blog/index', [
            'posts' => $posts,
            'tags' => $this->availableTags(),
            'filters' => $filters,
        ]);
    }

    public function show(BlogPost $post): Response
    {
        abort_unless($post->isPublished(), 404);

        $post->loadMissing('author:id,name');
        $post->increment('views_count');

        $related = BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->when($post->tags, function ($q) use ($post) {
                $q->where(function ($inner) use ($post) {
                    foreach ($post->tags as $tag) {
                        $inner->orWhereJsonContains('tags', $tag);
                    }
                });
            })
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'published_at']);

        return Inertia::render('blog/show', [
            'post' => $post,
            'contentHtml' => Str::markdown((string) $post->content, self::MARKDOWN_OPTIONS),
            'readingMinutes' => $this->readingMinutes((string) $post->content),
            'related' => $related,
        ]);
    }

    /**
     * Tags used by live posts, flattened from the JSON column.
     *
     * @return list<string>
     */
    private function availableTags(): array
    {
        $tags = BlogPost::query()
            ->published()
            ->pluck('tags')
            ->flatten()
            ->filter(fn ($tag) => is_string($tag) && $tag !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_map('strval', $tags);
    }

    private function readingMinutes(string $content): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($content)) / 200));
    }
}
