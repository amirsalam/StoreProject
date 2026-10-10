<?php

namespace Tests\Feature\Blog;

use App\Http\Middleware\SetLocale;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_posts_only(): void
    {
        $live = BlogPost::factory()->create(['title' => 'Live post', 'published_at' => now()->subDay()]);
        BlogPost::factory()->draft()->create(['title' => 'Draft post']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog/index')
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $live->id)
            );
    }

    public function test_index_hides_posts_scheduled_for_the_future(): void
    {
        BlogPost::factory()->create([
            'title' => 'Scheduled post',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->addWeek(),
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0));
    }

    public function test_index_filters_by_search_and_tag(): void
    {
        BlogPost::factory()->create([
            'title' => 'Shipping licenses',
            'tags' => ['laravel', 'release'],
            'published_at' => now()->subDay(),
        ]);
        BlogPost::factory()->create([
            'title' => 'Unrelated news',
            'tags' => ['saas'],
            'published_at' => now()->subDays(2),
        ]);

        $this->get(route('blog.index', ['search' => 'licenses']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Shipping licenses')
            );

        $this->get(route('blog.index', ['tag' => 'saas']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Unrelated news')
            );
    }

    public function test_show_renders_a_published_post_and_counts_the_view(): void
    {
        $post = BlogPost::factory()->create([
            'content' => "# Heading\n\nHello **world**.",
            'views_count' => 4,
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog/show')
                ->where('post.id', $post->id)
                ->where('contentHtml', fn (string $html) => str_contains($html, '<strong>world</strong>'))
                ->where('readingMinutes', 1)
            );

        $this->assertSame(5, $post->fresh()->views_count);
    }

    public function test_show_strips_html_embedded_in_post_content(): void
    {
        $post = BlogPost::factory()->create([
            'content' => 'Safe text <script>alert(1)</script> [link](javascript:alert(2))',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contentHtml', function (string $html) {
                    return ! str_contains($html, '<script>')
                        && ! str_contains($html, 'javascript:');
                })
            );
    }

    public function test_show_404s_for_draft_and_scheduled_posts(): void
    {
        $draft = BlogPost::factory()->draft()->create();
        $scheduled = BlogPost::factory()->create([
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->addWeek(),
        ]);

        $viewsBefore = $draft->views_count;

        $this->get(route('blog.show', $draft->slug))->assertNotFound();
        $this->get(route('blog.show', $scheduled->slug))->assertNotFound();

        // A 404 must not count as a view.
        $this->assertSame($viewsBefore, $draft->fresh()->views_count);
    }

    public function test_every_locale_defines_the_same_blog_keys(): void
    {
        $expected = array_keys(trans('messages.blog', [], 'en'));
        $this->assertNotEmpty($expected);

        foreach (SetLocale::SUPPORTED as $locale) {
            // fallback=false: a missing block must not silently resolve to en
            $actual = trans()->get('messages.blog', [], $locale, false);
            $this->assertIsArray($actual, "lang/{$locale}/messages.php has no 'blog' block");
            $this->assertSame($expected, array_keys($actual), "lang/{$locale}/messages.php 'blog' keys differ from en");
        }
    }
}
