<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A configurable payment provider managed from the admin dashboard.
 *
 * `credentials` (API keys, secrets, merchant ids — shape varies per
 * provider) and `webhook_secret` are encrypted at rest via the casts
 * below, so they are never stored or queried in plaintext. The provider
 * catalog + the credential field schema live in
 * config/payment_gateways.php so adding a provider needs no code change.
 */
class PaymentGateway extends Model
{
    use BelongsToTenant, HasFactory;

    public const ENV_SANDBOX = 'sandbox';

    public const ENV_PRODUCTION = 'production';

    protected $fillable = [
        'provider',
        'name',
        'display_name',
        'description',
        'logo',
        'is_active',
        'is_default',
        'environment',
        'credentials',
        'webhook_secret',
        'supported_currencies',
        'supported_countries',
        'fee_fixed',
        'fee_percent',
        'min_amount',
        'max_amount',
        'sort_order',
        'last_connection_at',
        'webhook_status',
    ];

    /**
     * Secret fields are NEVER exposed by default serialization. The
     * controller builds an explicit, redacted payload for the frontend.
     *
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'supported_currencies' => 'array',
            'supported_countries' => 'array',
            'fee_fixed' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'sort_order' => 'integer',
            'last_connection_at' => 'datetime',
        ];
    }

    /** @param  Builder<PaymentGateway>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Which credential fields currently have a stored value — booleans
     * only, never the secret values themselves. Safe to send to the UI so
     * it can render "configured" vs "not set" without leaking anything.
     *
     * @return array<string, bool>
     */
    public function credentialStatus(): array
    {
        $creds = $this->credentials ?? [];

        $status = [];
        foreach ($creds as $key => $value) {
            $status[$key] = filled($value);
        }

        return $status;
    }
}
