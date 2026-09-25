<?php

namespace App\Domain\Dashboard;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Single entry point for creating, reading, and dismissing in-app
 * notifications.
 *
 * Real-time broadcasting (websockets via Laravel Reverb) and email
 * delivery layer on top — `notify()` returns the persisted row and
 * leaves channel fan-out to listeners that subscribe to a
 * NotificationCreated event. The persisted row is the source of truth.
 */
class NotificationService
{
    /**
     * Persist a notification and return the row.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        string $level = Notification::LEVEL_INFO,
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?int $tenantId = null,
    ): Notification {
        return Notification::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Cursor-paginated feed for the bell dropdown.
     */
    public function feedFor(User $user, ?string $status = null, int $perPage = 25): CursorPaginator
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->when($status === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($status === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(Notification $notification): bool
    {
        return $notification->markRead();
    }

    public function markAllRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(Notification $notification): void
    {
        $notification->delete();
    }
}
