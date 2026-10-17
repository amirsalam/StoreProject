<?php

namespace App\Console\Commands;

use App\Domain\AppLicense\AppLicenseManager;
use Illuminate\Console\Command;

/** Show this installation's license state (local only, no network call). */
class AppLicenseStatusCommand extends Command
{
    protected $signature = 'app-license:status';

    protected $description = 'Show this installation\'s license state';

    public function handle(AppLicenseManager $manager): int
    {
        $s = $manager->status()->toArray();

        $this->table(['', ''], [
            ['State', $s['state'].($s['state_reason'] ? " ({$s['state_reason']})" : '')],
            ['This site (APP_URL)', $s['current_domain'] ?? '—'],
            ['Activated for', $s['bound_domain'] ?? '—'],
            ['Environment', $s['environment'] ?? '—'],
            ['Renewal due', $s['valid_until'] ?? '—'],
            ['Grace ends', $s['grace_ends_at'] ?? '—'],
            ['Last check', $s['last_checked_at'] ?? '—'],
            ['Last success', $s['last_success_at'] ?? '—'],
            ['Last error', $s['last_error_code'] ?? '—'],
            ['License server', $s['server_configured'] ? (string) config('app_license.server_url') : 'not configured'],
        ]);

        return self::SUCCESS;
    }
}
