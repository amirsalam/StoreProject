<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Translate a successful Socialite callback into a local User row.
 *
 * Resolution order on every callback:
 *   1. Existing SocialAccount row matching (provider, provider_id)
 *      → that user logs in, account row updated with the fresh payload.
 *   2. Existing User row matching email
 *      → link a new SocialAccount to them. They'll see "Continue with X"
 *      as a second sign-in option next time.
 *   3. Brand new user + brand new SocialAccount. Email is treated as
 *      pre-verified because the provider already validated ownership.
 */
class SocialiteService
{
    /**
     * Providers we accept callbacks from. Add new ones here AND in
     * config/services.php — the controller validates the incoming
     * route segment against this list.
     */
    public const SUPPORTED = ['google', 'github'];

    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED, true);
    }

    public function findOrCreateUser(string $provider, SocialiteUser $payload): User
    {
        return DB::transaction(function () use ($provider, $payload) {
            $providerId = (string) $payload->getId();
            $email = $this->normalizeEmail($payload->getEmail());

            // 1. Existing social account → fast path
            $existing = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();

            if ($existing) {
                $existing->update([
                    'provider_email' => $email,
                    'avatar_url' => $payload->getAvatar(),
                    'raw_data' => (array) ($payload->getRaw() ?? []),
                ]);

                return $existing->user;
            }

            // 2. Existing user by email → link
            $user = $email !== null ? User::query()->where('email', $email)->first() : null;

            // 3. Brand new user
            if (! $user) {
                // forceCreate: email_verified_at is deliberately not fillable.
                $user = User::query()->forceCreate([
                    'name' => $this->resolveName($payload, $email),
                    'email' => $email ?? "{$provider}-{$providerId}@users.noreply.local",
                    'password' => bcrypt(Str::random(40)),
                    'email_verified_at' => now(),
                ]);
            }

            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_email' => $email,
                'avatar_url' => $payload->getAvatar(),
                'raw_data' => (array) ($payload->getRaw() ?? []),
            ]);

            return $user->fresh();
        });
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }
        $trimmed = trim(strtolower($email));

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveName(SocialiteUser $payload, ?string $email): string
    {
        $name = $payload->getName() ?: $payload->getNickname();
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }
        if ($email !== null) {
            return Str::before($email, '@');
        }

        return 'New user';
    }
}
