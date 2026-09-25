<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal API tokens (Sanctum). Each token has a name, optional
 * abilities (scopes), and a last-used timestamp. The plaintext token is
 * shown ONCE on creation — after that only a token id + name + abilities
 * are kept around.
 */
class ApiTokenController extends Controller
{
    public const SUPPORTED_ABILITIES = [
        'read'   => 'Read your storefront data',
        'write'  => 'Create / update products and orders',
        'admin'  => 'Full admin access (treat as a password)',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/api-tokens', [
            'tokens' => $user->tokens()
                ->latest('id')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
                ->map(fn (PersonalAccessToken $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'abilities' => $t->abilities,
                    'last_used_at' => $t->last_used_at,
                    'created_at' => $t->created_at,
                ]),
            'abilities' => self::SUPPORTED_ABILITIES,
            // Surfaced once via flash on creation, then forgotten.
            'newToken' => session('newToken'),
            'newTokenName' => session('newTokenName'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'in:'.implode(',', array_keys(self::SUPPORTED_ABILITIES))],
        ]);

        $token = $request->user()->createToken(
            $data['name'],
            $data['abilities'],
        );

        ActivityLog::record('api_token.created', $request->user(), [
            'name' => $data['name'],
            'abilities' => $data['abilities'],
        ]);

        return redirect()
            ->route('api-tokens.index')
            ->with('newToken', $token->plainTextToken)
            ->with('newTokenName', $data['name'])
            ->with('success', 'API token created. Copy it now — you won\'t see it again.');
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->firstOrFail();
        $name = $token->name;
        $token->delete();

        ActivityLog::record('api_token.revoked', $request->user(), ['name' => $name]);

        return back()->with('success', "Token \"{$name}\" revoked.");
    }
}
