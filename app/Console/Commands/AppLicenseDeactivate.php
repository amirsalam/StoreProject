<?php

namespace App\Console\Commands;

use App\Domain\AppLicense\AppLicenseManager;
use App\Domain\AppLicense\AppLicenseMessages;
use App\Domain\AppLicense\LicenseServerException;
use Illuminate\Console\Command;

/** Release this installation's license so it can move to another server. */
class AppLicenseDeactivate extends Command
{
    protected $signature = 'app-license:deactivate {--force : Skip the confirmation prompt}';

    protected $description = 'Deactivate this installation\'s license (to move it elsewhere)';

    public function handle(AppLicenseManager $manager): int
    {
        if (! $manager->status()->activated) {
            $this->line('This installation is not activated.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Deactivate this installation? Licensed areas stop working until it is activated again.')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        try {
            $manager->deactivate();
        } catch (LicenseServerException $e) {
            $this->error(AppLicenseMessages::for($e->errorCode));

            return self::FAILURE;
        }

        $this->info('Deactivated. The license can now be activated on another installation.');

        return self::SUCCESS;
    }
}
