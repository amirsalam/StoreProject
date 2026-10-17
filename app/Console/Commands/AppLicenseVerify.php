<?php

namespace App\Console\Commands;

use App\Domain\AppLicense\AppLicenseManager;
use Illuminate\Console\Command;

/**
 * Check in with the license server. Scheduled hourly with --if-due
 * (routes/console.php), which calls the server only when a check-in is due.
 */
class AppLicenseVerify extends Command
{
    protected $signature = 'app-license:verify {--if-due : Only contact the server when a check-in is due}';

    protected $description = 'Check this installation\'s license with the license server';

    public function handle(AppLicenseManager $manager): int
    {
        $outcome = $this->option('if-due') ? $manager->refreshIfDue() : $manager->refresh();

        if ($outcome === null) {
            $this->line('No check-in due.');

            return self::SUCCESS;
        }

        $state = $manager->status()->toArray();

        match ($outcome) {
            'refreshed' => $this->info('License confirmed. Renewal due '.($state['valid_until'] ?? '—').'.'),
            'failed' => $this->warn("The license server could not be reached ({$state['last_error_code']}). The current license stays valid until its grace period ends."),
            'refused' => $this->error("The license server refused this license ({$state['last_error_code']})."),
            'released' => $this->error("This installation is no longer activated on the license server ({$state['last_error_code']}). Activate it again."),
            'domain_changed' => $this->warn('APP_URL no longer matches the activated domain. Activate this installation again.'),
            default => $this->line('This installation is not activated.'),
        };

        return in_array($outcome, ['refreshed', 'not_activated'], true) ? self::SUCCESS : self::FAILURE;
    }
}
