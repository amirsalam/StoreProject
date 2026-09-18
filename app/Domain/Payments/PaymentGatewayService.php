<?php

namespace App\Domain\Payments;

use App\Models\ActivityLog;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for managing payment-gateway configurations.
 *
 * Owns the rules the controller must never duplicate:
 *   - secret credentials are preserved when an edit submits a blank value
 *     (so admins don't have to re-enter API keys to change a fee),
 *   - exactly one gateway is the default per tenant,
 *   - every create/update/delete/toggle/default/test is audited.
 *
 * The provider catalog + credential field schema come from
 * config('payment_gateways.providers'), keeping new providers a
 * config-only change.
 */
class PaymentGatewayService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentGateway
    {
        $data['credentials'] = $this->cleanCredentials($data['provider'] ?? null, $data['credentials'] ?? []);

        $gateway = DB::transaction(function () use ($data) {
            $gateway = PaymentGateway::create($data);

            if ($gateway->is_default) {
                $this->demoteOtherDefaults($gateway);
            }

            return $gateway;
        });

        ActivityLog::record('payment_gateway.created', null, [
            'id' => $gateway->id,
            'provider' => $gateway->provider,
            'display_name' => $gateway->display_name,
        ]);

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentGateway $gateway, array $data): PaymentGateway
    {
        // Merge credentials so blank secret fields keep their stored value.
        $data['credentials'] = $this->mergeCredentials(
            $gateway,
            $data['provider'] ?? $gateway->provider,
            $data['credentials'] ?? [],
        );

        // A blank webhook secret on edit means "leave unchanged".
        if (array_key_exists('webhook_secret', $data) && blank($data['webhook_secret'])) {
            unset($data['webhook_secret']);
        }

        DB::transaction(function () use ($gateway, $data) {
            $gateway->update($data);

            if ($gateway->is_default) {
                $this->demoteOtherDefaults($gateway);
            }
        });

        ActivityLog::record('payment_gateway.updated', null, [
            'id' => $gateway->id,
            'provider' => $gateway->provider,
        ]);

        return $gateway;
    }

    public function delete(PaymentGateway $gateway): void
    {
        $snapshot = ['id' => $gateway->id, 'provider' => $gateway->provider, 'display_name' => $gateway->display_name];

        $gateway->delete();

        ActivityLog::record('payment_gateway.deleted', null, $snapshot);
    }

    public function setActive(PaymentGateway $gateway, bool $active): PaymentGateway
    {
        $gateway->update(['is_active' => $active]);

        ActivityLog::record($active ? 'payment_gateway.enabled' : 'payment_gateway.disabled', null, [
            'id' => $gateway->id,
            'provider' => $gateway->provider,
        ]);

        return $gateway;
    }

    public function setDefault(PaymentGateway $gateway): PaymentGateway
    {
        DB::transaction(function () use ($gateway) {
            $gateway->update(['is_default' => true, 'is_active' => true]);
            $this->demoteOtherDefaults($gateway);
        });

        ActivityLog::record('payment_gateway.default_set', null, [
            'id' => $gateway->id,
            'provider' => $gateway->provider,
        ]);

        return $gateway;
    }

    /**
     * @param  array<int, int>  $orderedIds  gateway ids in the desired order
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach (array_values($orderedIds) as $position => $id) {
                PaymentGateway::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        ActivityLog::record('payment_gateway.reordered', null, ['order' => array_values($orderedIds)]);
    }

    /**
     * Validate the configuration is complete enough to transact.
     *
     * Confirms every credential field marked `required` in the registry is
     * present. A live provider API ping is integration-specific and is left
     * to each gateway's wired client; this guards the common failure mode
     * (saved-but-incomplete) and records the check time on success.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(PaymentGateway $gateway): array
    {
        $fields = config("payment_gateways.providers.{$gateway->provider}.fields", []);
        $creds = $gateway->credentials ?? [];

        $missing = [];
        foreach ($fields as $key => $meta) {
            if (($meta['required'] ?? false) && blank($creds[$key] ?? null)) {
                $missing[] = $meta['label'] ?? $key;
            }
        }

        if ($missing !== []) {
            return ['ok' => false, 'message' => 'Missing required credentials: '.implode(', ', $missing)];
        }

        $gateway->update(['last_connection_at' => now()]);

        ActivityLog::record('payment_gateway.tested', null, [
            'id' => $gateway->id,
            'provider' => $gateway->provider,
        ]);

        return ['ok' => true, 'message' => 'Configuration looks complete.'];
    }

    private function demoteOtherDefaults(PaymentGateway $gateway): void
    {
        // BelongsToTenant global scope keeps this within the gateway's tenant.
        PaymentGateway::query()
            ->whereKeyNot($gateway->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Keep only credential keys the provider actually declares, dropping
     * blanks so we never store empty strings.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function cleanCredentials(?string $provider, array $submitted): array
    {
        $fields = config("payment_gateways.providers.{$provider}.fields", []);

        $clean = [];
        foreach ($fields as $key => $meta) {
            $value = $submitted[$key] ?? null;
            if (filled($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Merge submitted credentials over the stored ones: a present value
     * overwrites, a blank value preserves what's already saved. Lets an
     * admin edit non-secret settings without re-typing every API key.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function mergeCredentials(PaymentGateway $gateway, ?string $provider, array $submitted): array
    {
        $fields = config("payment_gateways.providers.{$provider}.fields", []);
        $existing = $gateway->credentials ?? [];

        $merged = [];
        foreach ($fields as $key => $meta) {
            $value = $submitted[$key] ?? null;
            if (filled($value)) {
                $merged[$key] = $value;
            } elseif (filled($existing[$key] ?? null)) {
                $merged[$key] = $existing[$key];
            }
        }

        return $merged;
    }
}
