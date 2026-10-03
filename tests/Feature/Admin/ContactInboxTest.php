<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContactInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_inbox(): void
    {
        $this->get('/admin/contact')->assertRedirect('/login');
    }

    public function test_non_admins_are_forbidden_from_the_inbox(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/contact')->assertForbidden();
    }

    public function test_admins_see_messages_and_the_unread_count(): void
    {
        $admin = User::factory()->admin()->create();
        ContactMessage::factory()->count(2)->create();
        ContactMessage::factory()->read()->create();

        $this->actingAs($admin)
            ->get('/admin/contact')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/contact/index')
                ->has('messages.data', 3)
                ->where('unreadCount', 2)
            );
    }

    public function test_the_inbox_filters_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        ContactMessage::factory()->create(['subject' => 'Unread one']);
        ContactMessage::factory()->read()->create(['subject' => 'Already read']);

        $this->actingAs($admin)
            ->get('/admin/contact?status=read')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('messages.data', 1)
                ->where('messages.data.0.subject', 'Already read')
            );
    }

    public function test_an_admin_can_mark_a_message_read_and_archive_it(): void
    {
        $admin = User::factory()->admin()->create();
        $message = ContactMessage::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/contact/{$message->id}", ['status' => ContactMessage::STATUS_READ])
            ->assertRedirect();

        $message->refresh();
        $this->assertSame(ContactMessage::STATUS_READ, $message->status);
        $this->assertNotNull($message->read_at);

        $readAt = $message->read_at;

        $this->actingAs($admin)
            ->patch("/admin/contact/{$message->id}", ['status' => ContactMessage::STATUS_ARCHIVED])
            ->assertRedirect();

        $message->refresh();
        $this->assertSame(ContactMessage::STATUS_ARCHIVED, $message->status);
        // Archiving keeps the original read timestamp.
        $this->assertTrue($readAt->equalTo($message->read_at));
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $message = ContactMessage::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/contact/{$message->id}", ['status' => 'spam'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ContactMessage::STATUS_NEW, $message->fresh()->status);
    }

    public function test_an_admin_can_delete_a_message(): void
    {
        $admin = User::factory()->admin()->create();
        $message = ContactMessage::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/contact/{$message->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_non_admins_cannot_delete_messages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $message = ContactMessage::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/contact/{$message->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);
    }
}
