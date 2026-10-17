<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * This installation's application-license state (one row). Not tenant
 * scoped. Use App\Domain\AppLicense\AppLicenseStateRepository, not this
 * model directly.
 *
 * @property string $installation_id
 * @property string $public_key base64 Ed25519 public key
 * @property string $secret_key raw Ed25519 secret key (decrypted by the cast)
 */
class AppLicenseState extends Model
{
    protected $table = 'app_license_state';

    protected $fillable = [
        'installation_id',
        'public_key',
        'secret_key',
        'activation_id',
        'bound_domain',
        'license_status',
        'environment',
        'token',
        'token_expires_at',
        'last_checked_at',
        'last_success_at',
        'last_error_code',
    ];

    protected $hidden = [
        'secret_key',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'secret_key' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }
}
