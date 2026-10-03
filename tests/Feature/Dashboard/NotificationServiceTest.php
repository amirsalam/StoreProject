<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Dashboard\NotificationService;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_persists_a_row(): void
    {
        $user = User::factory()->create();
        $row = app(NotificationService::class)->notify(
            $user,
            type: 'order.created',
            title: 'New order #42',
            body: '5 items worth $129',
            level: Notification::LEVEL_SUCCESS,
            actionUrl: '/orders/42',
        );

        $this->assertNotNull($row->id);
        $this->assertSame('order.created', $row->type);
        $this->assertSame(Notification::LEVEL_SUCCESS, $row->level);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->read_at);
    }

    public function test_unread_count_only_counts_unread(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();
        $other = User::factory()->create();

        $service->notify($user, 'a', 'A');
        $service->notify($user, 'b', 'B');
        $read = $service->notify($user, 'c', 'C');
        $service->markRead($read);

        // Notification for another user — must not be counted.
        $service->notify($other, 'd', 'D');

        $this->assertSame(2, $service->unreadCount($user));
    }

    public function test_mark_all_read_updates_only_owner_rows(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();
        $other = User::factory()->create();

        $service->notify($user, 'a', 'A');
        $service->notify($user, 'b', 'B');
        $service->notify($other, 'c', 'C');

        $updated = $service->markAllRead($user);

        $this->assertSame(2, $updated);
        $this->assertSame(0, $service->unreadCount($user));
        $this->assertSame(1, $service->unreadCount($other));
    }

    public function test_feed_filters_by_status(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();

        $service->notify($user, 'a', 'A');
        $service->notify($user, 'b', 'B');
        $read = $service->notify($user, 'c', 'C');
        $service->markRead($read);

        $unread = $service->feedFor($user, 'unread');
        $readOnly = $service->feedFor($user, 'read');
        $all = $service->feedFor($user);

        $this->assertCount(2, $unread->items());
        $this->assertCount(1, $readOnly->items());
        $this->assertCount(3, $all->items());
    }

    public function test_mark_read_is_a_no_op_on_already_read(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();
        $row = $service->notify($user, 'a', 'A');

        $this->assertTrue($service->markRead($row));
        $this->assertFalse($service->markRead($row->fresh()));
    }
}
