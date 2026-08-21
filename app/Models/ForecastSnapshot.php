<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastSnapshot extends Model
{
    protected $fillable = [
        'generated_on',
        'horizon_date',
        'lead_days',
        'scope',
        'scope_key',
        'predicted_orders',
        'predicted_units',
        'predicted_revenue',
        'units_p10',
        'units_p90',
        'actual_orders',
        'actual_units',
        'actual_revenue',
        'reconciled_at',
    ];

    protected $casts = [
        'generated_on'      => 'date',
        'horizon_date'      => 'date',
        'lead_days'         => 'integer',
        'predicted_orders'  => 'float',
        'predicted_units'   => 'float',
        'predicted_revenue' => 'float',
        'units_p10'         => 'float',
        'units_p90'         => 'float',
        'actual_orders'     => 'float',
        'actual_units'      => 'float',
        'actual_revenue'    => 'float',
        'reconciled_at'     => 'datetime',
    ];

    public function scopeReconciled($query)
    {
        return $query->whereNotNull('reconciled_at');
    }

    public function scopeScope($query, string $scope, ?string $key = null)
    {
        return $query->where('scope', $scope)->when($key !== null, fn ($q) => $q->where('scope_key', $key));
    }
}
