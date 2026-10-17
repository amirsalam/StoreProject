<?php

namespace App\Domain\AppLicense;

use Illuminate\Support\Str;

/**
 * Who this installation is to the license server: a UUID plus an Ed25519
 * key pair generated here. Only the public key is ever sent; the secret
 * key signs verify/deactivate requests and never leaves this database.
 */
final class InstallationIdentity
{
    public function __construct(
        public readonly string $installationId,
        public readonly string $publicKey,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {}

    public static function generate(): self
    {
        $pair = sodium_crypto_sign_keypair();
        $identity = new self(
            (string) Str::uuid(),
            base64_encode(sodium_crypto_sign_publickey($pair)),
            sodium_crypto_sign_secretkey($pair),
        );
        sodium_memzero($pair);

        return $identity;
    }

    /** Detached Ed25519 signature (raw bytes). */
    public function sign(string $message): string
    {
        return sodium_crypto_sign_detached($message, $this->secretKey);
    }

    /** For encrypted storage only (AppLicenseStateRepository). */
    public function exportSecretKey(): string
    {
        return base64_encode($this->secretKey);
    }

    public function __debugInfo(): array
    {
        return ['installationId' => $this->installationId, 'publicKey' => $this->publicKey, 'secretKey' => '[hidden]'];
    }
}
