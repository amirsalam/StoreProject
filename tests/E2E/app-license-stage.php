<?php

/**
 * One StoreProject action for the end-to-end licensing runner
 * (app-license-lifecycle.php). Not part of the PHPUnit suite.
 *
 * Runs with an environment prepared by the runner: its own SQLite database
 * (APP_URL = the installation's domain), so several "installations" can be
 * exercised side by side without touching the developer's database.
 *
 *   E2E_NOW_OFFSET  seconds to shift the clock (grace / expiry scenarios)
 *
 * Usage: php app-license-stage.php <action> [arg]
 *   migrate | activate <key> | refresh | deactivate | evaluate
 *   request <METHOD> <path> <guest|admin|buyer>
 *
 * Prints one line of JSON.
 */

use App\Domain\AppLicense\AppLicenseEvaluator;
use App\Domain\AppLicense\AppLicenseManager;
use App\Domain\AppLicense\LicenseServerException;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

[, $action] = $argv + [null, null];

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if ($offset = (int) getenv('E2E_NOW_OFFSET')) {
    Carbon::setTestNow(Carbon::now()->addSeconds($offset));
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds($offset));
}

$evaluate = function (): array {
    $evaluator = app(AppLicenseEvaluator::class);
    $evaluator->forget();
    $e = $evaluator->evaluate();

    return [
        'state' => $e->state,
        'reason' => $e->reason,
        'domain' => $e->token?->domain,
        'environment' => $e->token?->environment,
        'kid' => $e->token?->keyId,
        'license' => $e->token?->licenseId,
    ];
};

$out = match ($action) {
    'migrate' => (function () {
        Artisan::call('migrate', ['--force' => true]);
        User::factory()->admin()->create(['email' => 'e2e-admin@example.test']);
        User::factory()->create(['email' => 'e2e-buyer@example.test']);

        return ['ok' => true];
    })(),

    'activate' => (function () use ($argv, $evaluate) {
        try {
            app(AppLicenseManager::class)->activate($argv[2]);

            return ['ok' => true] + $evaluate();
        } catch (LicenseServerException $e) {
            return ['ok' => false, 'error' => $e->errorCode, 'definitive' => $e->isDefinitive()] + $evaluate();
        }
    })(),

    'refresh' => ['outcome' => app(AppLicenseManager::class)->refresh()] + $evaluate(),

    'deactivate' => (function () use ($evaluate) {
        try {
            app(AppLicenseManager::class)->deactivate();

            return ['ok' => true] + $evaluate();
        } catch (LicenseServerException $e) {
            return ['ok' => false, 'error' => $e->errorCode] + $evaluate();
        }
    })(),

    'evaluate' => $evaluate(),

    // A real request through StoreProject's HTTP kernel (routes + middleware).
    'request' => (function () use ($argv, $app) {
        [, , $method, $path, $as] = $argv + [null, null, 'GET', '/', 'guest'];
        $user = ['admin' => 'e2e-admin@example.test', 'buyer' => 'e2e-buyer@example.test'][$as] ?? null;
        if ($user) {
            Auth::guard('web')->setUser(User::query()->where('email', $user)->sole());
        }

        $request = Request::create($path, $method, server: ['HTTP_ACCEPT' => str_starts_with($path, '/api/') ? 'application/json' : 'text/html']);
        $response = $app->make(HttpKernel::class)->handle($request);
        $json = json_decode((string) $response->getContent(), true);

        return [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'error_code' => is_array($json) ? ($json['error']['code'] ?? null) : null,
        ];
    })(),

    default => ['error' => "unknown action: {$action}"],
};

echo json_encode($out, JSON_UNESCAPED_SLASHES), PHP_EOL;
