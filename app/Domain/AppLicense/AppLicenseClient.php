<?php

namespace App\Domain\AppLicense;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP client for IndependentLicenseServer's /api/v1/licenses endpoints.
 *
 *   activate    authenticated by the license key; registers this
 *               installation's public key
 *   verify      signed with the installation key (RequestSigner)
 *   deactivate  signed with the installation key
 *
 * Every failure becomes a LicenseServerException whose isDefinitive()
 * separates real answers from transient trouble. Redirects are never
 * followed (no silent downgrade to http or to another host).
 */
class AppLicenseClient
{
    private const ACTIVATE = '/api/v1/licenses/activate';

    private const VERIFY = '/api/v1/licenses/verify';

    private const DEACTIVATE = '/api/v1/licenses/deactivate';

    /** @throws LicenseServerException */
    public function activate(#[\SensitiveParameter] string $licenseKey, string $installationUrl, InstallationIdentity $identity): LicenseServerResponse
    {
        $data = $this->send(self::ACTIVATE, [
            'license_key' => $licenseKey,
            'product' => (string) config('app_license.product'),
            'url' => $installationUrl,
            'installation_id' => $identity->installationId,
            'installation_public_key' => $identity->publicKey,
            'app_version' => (string) config('app_license.app_version'),
        ]);

        $activation = $data['activation'] ?? null;
        if (! is_array($activation) || ! is_string($activation['id'] ?? null) || ! is_string($activation['domain'] ?? null)) {
            throw LicenseServerException::invalidResponse(200);
        }

        return $this->tokenResponse($data, $activation['id'], $activation['domain'], $activation['environment'] ?? null);
    }

    /** @throws LicenseServerException */
    public function verify(string $activationId, string $installationUrl, InstallationIdentity $identity): LicenseServerResponse
    {
        $data = $this->send(self::VERIFY, [
            'installation_id' => $identity->installationId,
            'url' => $installationUrl,
            'app_version' => (string) config('app_license.app_version'),
        ], $identity, $activationId);

        return $this->tokenResponse($data);
    }

    /**
     * @return bool true when the server changed something, false when the
     *              activation was already inactive (safe retry)
     *
     * @throws LicenseServerException
     */
    public function deactivate(string $activationId, InstallationIdentity $identity): bool
    {
        $data = $this->send(self::DEACTIVATE, ['installation_id' => $identity->installationId], $identity, $activationId);

        if (! is_bool($data['changed'] ?? null)) {
            throw LicenseServerException::invalidResponse(200);
        }

        return $data['changed'];
    }

    /** @return array<string, mixed> decoded JSON body of a 2xx answer */
    private function send(string $path, array $payload, ?InstallationIdentity $signer = null, ?string $activationId = null): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = $signer ? RequestSigner::headers($signer, $activationId, 'POST', $path, $body) : [];

        try {
            $response = $this->http()
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($this->baseUrl().$path);
        } catch (ConnectionException $e) {
            throw LicenseServerException::network($e->getMessage(), $e);
        } catch (LicenseServerException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw LicenseServerException::network(class_basename($e).': '.$e->getMessage(), $e);
        }

        return $this->decode($response);
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent('StoreProject-AppLicense/'.config('app_license.app_version'))
            ->connectTimeout((int) config('app_license.http.connect_timeout', 5))
            ->timeout((int) config('app_license.http.timeout', 10))
            ->withoutRedirecting()
            // Retry only when no answer arrived; an answer (even an error) is final.
            // Laravel passes null for failed responses that carry no exception
            // (e.g. 3xx), so the parameter must be nullable.
            ->retry(
                1 + (int) config('app_license.http.retries', 1),
                (int) config('app_license.http.retry_delay_ms', 300),
                fn (?Throwable $e) => $e instanceof ConnectionException,
                throw: false,
            );
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config('app_license.server_url'), '/');
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($url === '' || ! parse_url($url, PHP_URL_HOST) || ! in_array($scheme, ['http', 'https'], true)) {
            throw LicenseServerException::notConfigured('APP_LICENSE_SERVER_URL is not set to a valid URL.');
        }

        if ($scheme !== 'https' && ! app()->environment(['local', 'testing'])) {
            throw LicenseServerException::notConfigured('APP_LICENSE_SERVER_URL must use https.');
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $data = $response->json();

        if ($response->successful()) {
            if (! is_array($data)) {
                throw LicenseServerException::invalidResponse($response->status());
            }

            return $data;
        }

        $code = is_array($data) ? ($data['error']['code'] ?? null) : null;
        $message = is_array($data) ? ($data['error']['message'] ?? null) : null;

        if (! is_string($code) || preg_match('/^[a-z_]{1,64}$/', $code) !== 1) {
            // 5xx pages, proxies, captive portals… — not an answer from the server.
            throw $response->serverError()
                ? new LicenseServerException('server_error', 'The license server had an internal error.', $response->status())
                : LicenseServerException::invalidResponse($response->status());
        }

        throw new LicenseServerException($code, is_string($message) ? mb_substr($message, 0, 300) : $code, $response->status());
    }

    /** @param array<string, mixed> $data */
    private function tokenResponse(array $data, ?string $activationId = null, ?string $domain = null, mixed $environment = null): LicenseServerResponse
    {
        $token = $data['token'] ?? null;
        $expires = $data['token_expires_at'] ?? null;
        $status = $data['license']['status'] ?? null;

        if (! is_string($token) || substr_count($token, '.') !== 2 || ! is_string($expires) || ! is_string($status)) {
            throw LicenseServerException::invalidResponse(200);
        }

        try {
            $expiresAt = CarbonImmutable::parse($expires);
        } catch (Throwable) {
            throw LicenseServerException::invalidResponse(200);
        }

        return new LicenseServerResponse($token, $expiresAt, $status, $activationId, $domain, is_string($environment) ? $environment : null);
    }
}
