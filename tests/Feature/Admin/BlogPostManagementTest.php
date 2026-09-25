<?php

namespace Tests\Feature\Admin;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BlogPostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_admin_blog_index(): void
    {
        $this->get('/admin/blog-posts')->assertRedirect('/login');
    }

    public function test_non_admins_are_forbidden_from_admin_blog_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/blog-posts')->assertForbidden();
    }

    public function test_admins_see_drafts_and_published_posts(): void
    {
        $admin = User::factory()->admin()->create();
        BlogPost::factory()->count(2)->create();
        BlogPost::factory()->draft()->create();

        $this->actingAs($admin)
            ->get('/admin/blog-posts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/blog/index')
                ->has('posts.data', 3)
            );
    }

    public function test_admin_can_create_a_post_with_derived_slug_and_tags(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/blog-posts', [
                'title' => 'Shipping the new API',
                'slug' => '',
                'excerpt' => 'What changed and why.',
                'content' => 'The **details**.',
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => '',
                'tags' => 'laravel, release, laravel',
            ])
            ->assertRedirect('/admin/blog-posts');

        $post = BlogPost::query()->sole();
        $this->assertSame('shipping-the-new-api', $post->slug);
        $this->assertSame($admin->id, $post->user_id);
        // Duplicates collapse, and publishing without a date fills in "now".
        $this->assertSame(['laravel', 'release'], $post->tags);
        $this->assertNotNull($post->published_at);
        $this->assertTrue($post->isPublished());
    }

    public function test_a_draft_does_not_get_a_publish_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/blog-posts', [
            'title' => 'Work in progress',
            'content' => 'Later.',
            'status' => BlogPost::STATUS_DRAFT,
        ])->assertRedirect('/admin/blog-posts');

        $post = BlogPost::query()->sole();
        $this->assertNull($post->published_at);
        $this->assertFalse($post->isPublished());
    }

    public function test_slug_must_be_unique_across_posts(): void
    {
        $admin = User::factory()->admin()->create();
        BlogPost::factory()->create(['slug' => 'taken-slug']);

        $this->actingAs($admin)
            ->post('/admin/blog-posts', [
                'title' => 'Another post',
                'slug' => 'taken-slug',
                'content' => 'Body.',
                'status' => BlogPost::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_a_post_keeping_its_own_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $post = BlogPost::factory()->create(['slug' => 'keep-me', 'title' => 'Old title']);

        $this->actingAs($admin)
            ->put("/admin/blog-posts/{$post->id}", [
                'title' => 'New title',
                'slug' => 'keep-me',
                'content' => 'Updated body.',
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => $post->published_at->toDateTimeString(),
            ])
            ->assertRedirect('/admin/blog-posts')
            ->assertSessionHasNoErrors();

        $this->assertSame('New title', $post->fresh()->title);
    }

    public function test_admin_can_delete_a_post(): void
    {
        $admin = User::factory()->admin()->create();
        $post = BlogPost::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/blog-posts/{$post->id}")
            ->assertRedirect('/admin/blog-posts');

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_non_admins_cannot_create_posts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post('/admin/blog-posts', [
                'title' => 'Nope',
                'content' => 'Body.',
                'status' => BlogPost::STATUS_DRAFT,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('blog_posts', 0);
    }
}
