<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revoking a personal API token from Settings → API tokens. (The 419 this
 * button used to hit was client-side — a hand-built form with no CSRF
 * token — and can't be reproduced here, since tests skip CSRF.)
 */
class ApiTokenRevokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('CI deploy', ['read'])->accessToken;

        $this->actingAs($user)
            ->from(route('api-tokens.index'))
            ->delete(route('api-tokens.destroy', $token->id))
            ->assertRedirect(route('api-tokens.index'))
            ->assertSessionHas('success', 'Token "CI deploy" revoked.');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'api_token.revoked', 'user_id' => $user->id]);
    }

    public function test_a_user_cannot_revoke_someone_elses_token(): void
    {
        $owner = User::factory()->create();
        $token = $owner->createToken('Theirs')->accessToken;

        $this->actingAs(User::factory()->create())
            ->delete(route('api-tokens.destroy', $token->id))
            ->assertNotFound();

        $this->assertSame(1, $owner->tokens()->count());
    }
}
