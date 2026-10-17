<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    use BelongsToTenant, HasFactory;

    // Metric key catalog. Add new keys here so callers can use the
    // constants instead of stringly-typed names.
    public const KEY_REVENUE_CENTS = 'revenue_cents';
    public const KEY_ORDERS_COUNT = 'orders_count';
    public const KEY_NEW_CUSTOMERS = 'new_customers';
    public const KEY_REFUNDS_CENTS = 'refunds_cents';
    public const KEY_PRODUCT_VIEWS = 'product_views';
    public const KEY_VENDOR_SIGNUPS = 'vendor_signups';
    public const KEY_ACTIVE_SUBSCRIPTIONS = 'active_subscriptions';

    protected $fillable = [
        'tenant_id',
        'metric_key',
        'dimension_key',
        'value',
        'currency',
        'recorded_on',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'recorded_on' => 'date',
        ];
    }
}
