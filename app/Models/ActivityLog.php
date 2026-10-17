<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Audit trail for authentication and security events.
 *
 * Use the static record() helper from controllers / observers — it
 * pulls IP + user agent from the request automatically:
 *
 *   ActivityLog::record('auth.login', $user);
 *   ActivityLog::record('password.changed', $user, ['reason' => 'rotation']);
 *   ActivityLog::record('auth.login.failed', null, ['email' => $email]);
 */
class ActivityLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null; // append-only

    protected $fillable = [
        'user_id',
        'event',
        'description',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Persist a new entry. $user can be null for failed-login-style events
     * where the user identity isn't trustworthy.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        ?User $user = null,
        array $properties = [],
        ?string $description = null,
    ): self {
        $user ??= Auth::user();
        $request = request();

        return self::create([
            'user_id' => $user?->id,
            'event' => $event,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request
                ? mb_substr((string) $request->userAgent(), 0, 512)
                : null,
        ]);
    }
}
