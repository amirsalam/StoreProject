<?php

/**
 * End-to-end licensing test: the REAL IndependentLicenseServer (running)
 * and REAL StoreProject code, the 13-point lifecycle from the roadmap.
 * Not part of the PHPUnit suite (it needs a running license server).
 *
 *   php tests/E2E/app-license-lifecycle.php [server-path] [server-url]
 *
 * Defaults: ../IndependentLicenseServer and http://localhost:8002.
 *
 * - Server actions use the server's own artisan commands, in a CLEAN
 *   environment (StoreProject's variables must never leak into it).
 * - Each StoreProject "installation" runs in its own process with its own
 *   temporary SQLite database; the developer's database is never touched.
 * - Leaves test licenses (e2e-…@example.test) on the license server.
 *
 * Exit code 0 when every check passes.
 */
$storeProject = realpath(__DIR__.'/../..');
$server = realpath($argv[1] ?? $storeProject.'/../IndependentLicenseServer');
$serverUrl = rtrim($argv[2] ?? 'http://localhost:8002', '/');
$php = PHP_BINARY;
$run = date('YmdHis');
$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'app-license-e2e-'.$run;
@mkdir($tmp, 0700, true);

$results = [];
$failures = 0;

/** Minimal environment: nothing from StoreProject (or this shell) leaks in. */
function cleanEnv(array $extra = []): array
{
    $keep = [];
    foreach (['PATH', 'Path', 'SystemRoot', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP', 'HOME', 'USERPROFILE', 'COMSPEC', 'APPDATA', 'LOCALAPPDATA'] as $name) {
        if (($value = getenv($name)) !== false) {
            $keep[$name] = $value;
        }
    }

    return array_merge($keep, $extra);
}

function runProcess(array $command, string $cwd, array $env): array
{
    $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($proc), trim(preg_replace('/\e\[[0-9;]*m/', '', $out."\n".$err))];
}

/** The license server's own CLI. */
function serverCmd(string ...$args): string
{
    global $php, $server;

    return runProcess([$php, '-d', 'xdebug.mode=off', 'artisan', ...$args], $server, cleanEnv())[1];
}

/** A PHP expression evaluated inside the server app; returns decoded JSON. */
function serverQuery(string $phpExpression): mixed
{
    $out = serverCmd('tinker', '--execute=echo "@@".json_encode('.$phpExpression.');');

    return json_decode(substr($out, (int) strrpos($out, '@@') + 2), true);
}

/** One StoreProject action, as installation $name on $domain. */
function stage(string $name, string $domain, array $args, array $env = []): array
{
    global $php, $storeProject, $tmp, $serverUrl;

    $db = $tmp.DIRECTORY_SEPARATOR.$name.'.sqlite';
    if (! file_exists($db)) {
        touch($db);
    }

    [, $out] = runProcess(
        [$php, '-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'xdebug.mode=off', 'tests/E2E/app-license-stage.php', ...$args],
        $storeProject,
        cleanEnv(array_merge([
            'APP_ENV' => 'local',
            'APP_URL' => "https://{$domain}",
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $db,
            'APP_LICENSE_SERVER_URL' => $serverUrl,
            'APP_LICENSE_ISSUER' => $serverUrl,
            'APP_LICENSE_ENFORCE' => 'true',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'LOG_CHANNEL' => 'null',
        ], $env)),
    );

    $lines = array_values(array_filter(explode("\n", $out)));
    $decoded = json_decode((string) end($lines), true);

    return is_array($decoded) ? $decoded : ['raw' => $out];
}

function check(string $step, string $name, bool $ok, mixed $detail = null): void
{
    global $results, $failures;

    $results[] = [$step, $name, $ok, $detail];
    $failures += $ok ? 0 : 1;
    printf("  %s  %-6s %s%s\n", $ok ? 'PASS' : 'FAIL', $step, $name, $ok ? '' : '  → '.json_encode($detail, JSON_UNESCAPED_SLASHES));
}

function issueLicense(string $tag): array
{
    global $run;

    $out = serverCmd('licenses:issue', 'storeproject', "--email=e2e-{$tag}-{$run}@example.test", "--order=E2E-{$tag}-{$run}", '--notes=Automated end-to-end test');
    preg_match('/License (\w{26}) issued/', $out, $id);
    preg_match('/SP(-[0-9A-Z]{5}){6}/', $out, $key);

    return ['id' => $id[1] ?? null, 'key' => $key[0] ?? null];
}

// ---------------------------------------------------------------------------------------------

echo "App license end-to-end test — server {$serverUrl}\n\n";

$ping = @file_get_contents($serverUrl.'/api/v1/ping');
if (! $ping || ! str_contains($ping, 'independent-license-server')) {
    fwrite(STDERR, "The license server is not reachable at {$serverUrl}.\n");
    exit(2);
}

// 1. Issue a license.
$license = issueLicense('main');
check('1', 'Issue a license on the server', $license['id'] && $license['key'], $license['id']);

// 2. Activate StoreProject on example.com.
stage('A', 'example.com', ['migrate']);
$a = stage('A', 'example.com', ['activate', $license['key']]);
check('2', 'Activate on example.com (production)', ($a['ok'] ?? false) && $a['state'] === 'licensed' && $a['domain'] === 'example.com' && $a['environment'] === 'production', $a);

// 3. Verify the signed license.
$a = stage('A', 'example.com', ['refresh']);
check('3', 'Check-in returns a verified signed token (dev key)', $a['outcome'] === 'refreshed' && $a['state'] === 'licensed' && $a['kid'] === 'dev-2026-10' && $a['license'] === $license['id'], $a);
$verified = serverQuery("App\\Models\\License::where('public_id', '{$license['id']}')->sole()->auditLogs()->where('event', 'activation.verified')->count()");
check('3', 'Server recorded the verification', $verified >= 1, $verified);

// 4–5. Second production domain while the first is active.
stage('X', 'another-example.com', ['migrate']);
$x = stage('X', 'another-example.com', ['activate', $license['key']]);
check('4', 'Activation on a second production domain is attempted', isset($x['ok']), $x);
check('5', 'Second domain rejected (domain_limit_reached, definitive)', ($x['ok'] ?? true) === false && $x['error'] === 'domain_limit_reached' && $x['definitive'] === true && $x['state'] === 'not_activated', $x);
$active = serverQuery("App\\Models\\License::where('public_id', '{$license['id']}')->sole()->activeActivations()->pluck('canonical_domain')");
check('5', 'Server still has exactly one live domain: example.com', $active === ['example.com'], $active);

// 6. Deactivate the original installation.
$a = stage('A', 'example.com', ['deactivate']);
check('6', 'Deactivate example.com', ($a['ok'] ?? false) && $a['state'] === 'not_activated', $a);

// 7. Activate on the replacement domain.
stage('B', 'replacement-example.com', ['migrate']);
$b = stage('B', 'replacement-example.com', ['activate', $license['key']]);
check('7', 'Activate on replacement-example.com', ($b['ok'] ?? false) && $b['state'] === 'licensed' && $b['domain'] === 'replacement-example.com', $b);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/admin/products', 'admin']);
check('7', 'Licensed admin area works (200)', $req['status'] === 200, $req);

// 8–9. Suspend; enforcement.
serverCmd('licenses:status', $license['id'], 'suspend', '--reason=E2E suspension');
check('8', 'Suspend the license on the server', serverQuery("App\\Models\\License::where('public_id', '{$license['id']}')->sole()->status->value") === 'suspended');
$b = stage('B', 'replacement-example.com', ['refresh']);
check('9', 'Next check-in: unlicensed immediately (license_suspended)', $b['outcome'] === 'refused' && $b['state'] === 'unlicensed' && $b['reason'] === 'license_suspended', $b);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/admin/products', 'admin']);
check('9', 'Admin area redirects to the activation page', $req['status'] === 302 && str_ends_with((string) $req['location'], '/admin/app-license'), $req);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/checkout', 'buyer']);
check('9', 'Buyer checkout gets 503', $req['status'] === 503, $req);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/admin/app-license', 'admin']);
check('9', 'Activation page stays reachable', $req['status'] === 200, $req);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/', 'guest']);
check('9', 'Storefront stays open', $req['status'] === 200, $req);

// 10. Reinstate.
serverCmd('licenses:status', $license['id'], 'reinstate');
$b = stage('B', 'replacement-example.com', ['refresh']);
check('10', 'Reinstated: next check-in is licensed again', $b['outcome'] === 'refreshed' && $b['state'] === 'licensed', $b);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/admin/products', 'admin']);
check('10', 'Admin area works again (200)', $req['status'] === 200, $req);

// 11. Revoke.
serverCmd('licenses:status', $license['id'], 'revoke', '--reason=E2E revocation', '--force');
$b = stage('B', 'replacement-example.com', ['refresh']);
check('11', 'Revoked: next check-in is unlicensed (license_revoked)', $b['outcome'] === 'refused' && $b['state'] === 'unlicensed' && $b['reason'] === 'license_revoked', $b);
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/admin/products', 'admin']);
check('11', 'Admin area locked after revocation', $req['status'] === 302, $req);

// 12. Expired tokens and offline behaviour (fresh license, installation C).
$second = issueLicense('offline');
stage('C', 'offline-example.com', ['migrate']);
$c = stage('C', 'offline-example.com', ['activate', $second['key']]);
check('12', 'Activate a second license on offline-example.com', ($c['ok'] ?? false) && $c['state'] === 'licensed', $c);
$offline = ['APP_LICENSE_SERVER_URL' => 'http://127.0.0.1:9'];
$c = stage('C', 'offline-example.com', ['refresh'], $offline);
check('12', 'Server unreachable: check-in fails, still licensed', $c['outcome'] === 'failed' && $c['state'] === 'licensed', $c);
$c = stage('C', 'offline-example.com', ['evaluate'], $offline + ['E2E_NOW_OFFSET' => (string) (73 * 3600)]);
check('12', 'Token expired (73 h later), still offline: grace period', $c['state'] === 'grace', $c);
$c = stage('C', 'offline-example.com', ['request', 'GET', '/admin/products', 'admin'], $offline + ['E2E_NOW_OFFSET' => (string) (73 * 3600)]);
check('12', 'Grace keeps the admin area working', $c['status'] === 200, $c);
$c = stage('C', 'offline-example.com', ['evaluate'], $offline + ['E2E_NOW_OFFSET' => (string) ((72 + 15 * 24) * 3600)]);
check('12', 'Grace over (15 days offline): unlicensed', $c['state'] === 'unlicensed' && $c['reason'] === 'grace_expired', $c);
$c = stage('C', 'offline-example.com', ['refresh']);
check('12', 'Server reachable again: recovered (licensed)', $c['outcome'] === 'refreshed' && $c['state'] === 'licensed', $c);
serverCmd('licenses:extend', $second['id'], '--until='.date('Y-m-d', strtotime('+1 day')));
$c = stage('C', 'offline-example.com', ['refresh']);
$c = stage('C', 'offline-example.com', ['evaluate'], ['E2E_NOW_OFFSET' => (string) (3 * 86400)]);
check('12', 'License past its own expiry date: unlicensed (license_expired)', $c['state'] === 'unlicensed' && $c['reason'] === 'license_expired', $c);
stage('C', 'offline-example.com', ['deactivate']);

// 13. StoreProject's product licensing is unaffected.
$req = stage('B', 'replacement-example.com', ['request', 'GET', '/api/v1/licenses/validate', 'guest']);
check('13', 'Product-license API answers while unlicensed (no app_license_required)', $req['error_code'] !== 'app_license_required' && $req['status'] !== 503, $req);
[$code, $out] = runProcess([$php, '-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'xdebug.mode=off', 'vendor/bin/phpunit', '--filter', 'LicenseActivationApiTest', '--no-progress'], $storeProject, cleanEnv());
preg_match('/(OK \(.*\)|Tests: .*)/', $out, $summary);
check('13', 'Product licensing test suite passes', $code === 0, $summary[0] ?? $out);

// ---------------------------------------------------------------------------------------------

array_map('unlink', glob($tmp.DIRECTORY_SEPARATOR.'*') ?: []);
@rmdir($tmp);

$total = count($results);
echo "\n".($failures === 0 ? "ALL {$total} CHECKS PASSED" : "{$failures} OF {$total} CHECKS FAILED")."\n";
exit($failures === 0 ? 0 : 1);
