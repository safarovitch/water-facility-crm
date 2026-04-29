<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\HasHumanTimestamps;

class RawMaterial extends Model
{
    use HasFactory, HasHumanTimestamps;

    protected $fillable = [
        'name',
        'sku',
        'unit',
        'current_stock',
        'cost_per_unit',
        'status',
        'is_reusable',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
        'is_reusable' => 'boolean',
    ];

    protected $appends = [
        'created_at_human',
        'created_at_formatted',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function returnedOrders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_returned_materials')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
