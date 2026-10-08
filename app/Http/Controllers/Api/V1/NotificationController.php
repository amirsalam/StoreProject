<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\NotificationService;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST endpoints for the notification center.
 *
 * Auth: Sanctum personal access token.
 * Tenant isolation: BelongsToTenant scope on Notification — all reads
 * are automatically narrowed to the resolved tenant.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->string('status')->toString() ?: null;
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $page = $this->service->feedFor($user, $status, $perPage);

        return response()->json([
            'data' => $page->items(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'unread_count' => $this->service->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->service->markRead($notification);
        return response()->json(['data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->service->markAllRead($request->user());
        return response()->json(['updated' => $updated]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->service->delete($notification);
        return response()->json(['deleted' => true]);
    }
}
