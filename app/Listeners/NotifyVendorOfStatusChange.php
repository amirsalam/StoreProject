<?php

namespace App\Listeners;

use App\Domain\Dashboard\NotificationService;
use App\Events\VendorStatusChanged;
use App\Models\Notification;

/**
 * Tells a vendor's owner, in-app, that an admin moderated their store
 * (marketplace doc §11/§14). A rejection or suspension reason, when the
 * admin gave one, becomes the notification body.
 *
 * Email delivery layers on later via the notifications module's
 * channel fan-out — the persisted in-app row is the source of truth.
 *
 * Auto-discovered (App\Listeners, type-hinted handle()) — do not also
 * register it with Event::listen, or it runs twice.
 */
class NotifyVendorOfStatusChange
{
    /**
     * action => [title, level].
     */
    private const MESSAGES = [
        'approved' => ['Your store has been approved', Notification::LEVEL_SUCCESS],
        'rejected' => ['Your store application was not approved', Notification::LEVEL_WARNING],
        'suspended' => ['Your store has been suspended', Notification::LEVEL_CRITICAL],
        'reinstated' => ['Your store has been reinstated', Notification::LEVEL_SUCCESS],
    ];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(VendorStatusChanged $event): void
    {
        $vendor = $event->vendor;
        $owner = $vendor->owner;

        if (! $owner || ! isset(self::MESSAGES[$event->action])) {
            return;
        }

        [$title, $level] = self::MESSAGES[$event->action];

        $this->notifications->notify(
            user: $owner,
            type: "vendor.{$event->action}",
            title: $title,
            body: $event->reason,
            level: $level,
            actionUrl: route('workspace.vendor.edit', absolute: false),
            metadata: ['vendor_id' => $vendor->id, 'from' => $event->fromStatus, 'to' => $vendor->status],
            tenantId: $vendor->tenant_id,
        );
    }
}
