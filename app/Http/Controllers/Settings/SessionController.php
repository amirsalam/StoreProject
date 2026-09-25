<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    /**
     * Show every session row for the current user, with current device flagged.
     */
    public function index(Request $request): Response
    {
        $current = $request->session()->getId();
        $rows = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return Inertia::render('settings/sessions', [
            'sessions' => $rows->map(fn ($row) => $this->present($row, $current)),
            'driver' => config('session.driver'),
        ]);
    }

    /**
     * Revoke a single session by id. Refuses to delete the current one
     * (use the "logout other" flow if you want to clear them all).
     */
    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        $current = $request->session()->getId();
        if ($sessionId === $current) {
            return back()->with('error', 'Cannot revoke the current session here. Use Logout instead.');
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        ActivityLog::record('session.revoked', $request->user(), [
            'session_id' => substr(hash('sha256', $sessionId), 0, 10),
        ]);

        return back()->with('success', 'Session revoked.');
    }

    /**
     * Sign the user out of every device except the current one. Requires
     * the password to defeat session-hijacking shenanigans.
     */
    public function destroyOther(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $current = $request->session()->getId();
        $count = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $current)
            ->delete();

        Auth::logoutOtherDevices($request->input('password'));

        ActivityLog::record('session.revoked_others', $request->user(), ['count' => $count]);

        return back()->with('success', "Signed out from {$count} other " . ($count === 1 ? 'session' : 'sessions') . '.');
    }

    /**
     * Decorate a session row with a friendly device label + timestamps.
     */
    private function present(object $row, string $currentId): array
    {
        return [
            'id' => $row->id,
            'ip_address' => $row->ip_address,
            'device' => $this->parseAgent($row->user_agent ?? ''),
            'is_current' => $row->id === $currentId,
            'last_active_at' => $row->last_activity,
        ];
    }

    /**
     * Best-effort OS + browser parsing from a User-Agent string. Keeps
     * the dependency footprint at zero (no jenssegers/agent, no UA-parser).
     */
    private function parseAgent(string $ua): string
    {
        if ($ua === '') {
            return 'Unknown device';
        }

        $os = match (true) {
            (bool) preg_match('/Windows NT 10/i', $ua) => 'Windows 10/11',
            (bool) preg_match('/Windows NT 6\.3/i', $ua) => 'Windows 8.1',
            (bool) preg_match('/Windows NT 6\.[0-2]/i', $ua) => 'Windows 7/8',
            (bool) preg_match('/Mac OS X 10[._]([0-9]+)/i', $ua, $m) => 'macOS 10.' . $m[1],
            (bool) preg_match('/Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Android ([0-9]+)/i', $ua, $m) => 'Android ' . $m[1],
            (bool) preg_match('/iPhone OS ([0-9]+)/i', $ua, $m) => 'iOS ' . $m[1],
            (bool) preg_match('/iPad/i', $ua) => 'iPadOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Unknown OS',
        };

        // Order matters — Edge contains Chrome, Chrome contains Safari, Opera contains Chrome.
        $browser = match (true) {
            (bool) preg_match('/EdgA?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera\//i', $ua) => 'Opera',
            (bool) preg_match('/Firefox\//i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\//i', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//i', $ua) => 'Safari',
            default => 'Browser',
        };

        return $os . ' · ' . $browser;
    }
}
