<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * Shared input massaging for the blog-post store/update requests:
 * slug defaulting, tags as either a comma-separated string or an array,
 * and a publish date that fills itself in when a post goes live.
 */
trait NormalizesBlogPostInput
{
    /**
     * @return array<string, mixed>
     */
    protected function normalizedBlogPostInput(): array
    {
        $slug = (string) $this->input('slug', '');
        $title = (string) $this->input('title', '');

        $merged = [
            'slug' => $slug !== '' ? Str::slug($slug) : ($title !== '' ? Str::slug($title) : null),
            'tags' => $this->normalizedTags(),
        ];

        // Publishing without picking a date means "now"; a date the author
        // set (including a future one, which stays hidden until it passes)
        // is left alone.
        if ($this->input('status') === BlogPost::STATUS_PUBLISHED && ! $this->filled('published_at')) {
            $merged['published_at'] = now();
        }

        return $merged;
    }

    /**
     * @return list<string>|null
     */
    private function normalizedTags(): ?array
    {
        $tags = $this->input('tags');

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (! is_array($tags)) {
            return null;
        }

        $clean = [];
        foreach ($tags as $tag) {
            if (! is_string($tag) && ! is_numeric($tag)) {
                continue;
            }
            $tag = trim((string) $tag);
            if ($tag !== '' && ! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
