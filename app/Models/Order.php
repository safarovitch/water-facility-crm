<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
  use HasHumanTimestamps;

  protected $fillable = [
    'order_number',
    'user_id',
    'parent_order_id',
    'contact_name',
    'contact_phone',
    'status',
    'scheduled_delivery_at',
    'actual_delivery_at',
    'delivery_address',
    'total_amount',
    'discount_amount',
    'deposit_charge',
    'paid_amount',
    'payment_status',
    'notes',
    'cancellation_reason',
    'cancelled_at',
    'cancelled_by',
    'created_by',
    'courier_id',
    'lat',
    'lng',
  ];

  protected $casts = [
    'status'                => OrderStatus::class,
    'payment_status'        => PaymentStatus::class,
    'scheduled_delivery_at' => 'datetime',
    'actual_delivery_at'    => 'datetime',
    'cancelled_at'          => 'datetime',
    'total_amount'          => 'decimal:2',
    'discount_amount'       => 'decimal:2',
    'deposit_charge'        => 'decimal:2',
    'paid_amount'           => 'decimal:2',
    'lat'                   => 'float',
    'lng'                   => 'float',
  ];

  protected $appends = [
    'balance_due',
    'created_at_human',
    'created_at_formatted',
    'updated_at_human',
    'updated_at_formatted',
    'scheduled_delivery_at_human',
    'scheduled_delivery_at_formatted',
    'actual_delivery_at_human',
    'actual_delivery_at_formatted',
    'cancelled_at_human',
    'cancelled_at_formatted',
  ];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (Order $order) {
      $order->order_number = static::generateOrderNumber();
    });

    static::created(function (Order $order) {
      event(new \App\Events\OrderCreated($order));
    });
  }

  public static function generateOrderNumber(): string
  {
    $year   = now()->format('Y');
    $latest = static::whereYear('created_at', $year)->max('id') ?? 0;

    return 'WF-' . $year . '-' . str_pad($latest + 1, 5, '0', STR_PAD_LEFT);
  }

  public function getBalanceDueAttribute(): float
  {
    return (float) $this->total_amount + (float) $this->deposit_charge - (float) $this->paid_amount;
  }

  /**
   * For each reusable raw material used by the items in this order, return:
   *   raw_material_id => [
   *     'raw_material'   => RawMaterial model,
   *     'expected'       => float total units the BOM consumed,
   *     'returned'       => int units recorded as returned,
   *     'missing'        => float max(expected - returned, 0),
   *     'charge'         => float deposit_price × missing,
   *   ]
   *
   * Used at delivery time to bill clients for bottles they didn't hand
   * back, and to surface the breakdown to admins.
   */
  public function reusableDepositSummary(): array
  {
    $this->loadMissing(['items.product.rawMaterials']);

    $expected = [];   // rm_id => float
    $materials = [];  // rm_id => RawMaterial

    foreach ($this->items as $item) {
      // Once delivered, only count containers we actually handed over —
      // a short-shipped bottle isn't a missing return, it's a missing
      // delivery. Pre-delivery (delivered_quantity null) we fall back to
      // the ordered amount so admins can preview the expected return set.
      $countedQty = $item->delivered_quantity ?? $item->quantity;
      if ($countedQty <= 0) {
        continue;
      }
      foreach ($item->product->rawMaterials ?? [] as $material) {
        if (! $material->is_reusable) {
          continue;
        }
        $perUnit = (float) ($material->pivot->quantity ?? 0);
        if ($perUnit <= 0) {
          continue;
        }
        $expected[$material->id] = ($expected[$material->id] ?? 0)
          + ($perUnit * (int) $countedQty);
        $materials[$material->id] = $material;
      }
    }

    if (empty($expected)) {
      return [];
    }

    $this->loadMissing('returnedMaterials');
    $returned = [];
    foreach ($this->returnedMaterials as $rm) {
      $returned[$rm->id] = ($returned[$rm->id] ?? 0) + (int) ($rm->pivot->quantity ?? 0);
    }

    $summary = [];
    foreach ($expected as $rmId => $exp) {
      $ret = (int) ($returned[$rmId] ?? 0);
      $missing = max($exp - $ret, 0);
      $material = $materials[$rmId];
      $summary[$rmId] = [
        'raw_material' => $material,
        'expected'     => $exp,
        'returned'     => $ret,
        'missing'      => $missing,
        'charge'       => round($missing * (float) $material->deposit_price, 2),
      ];
    }

    return $summary;
  }

  /**
   * Get the human-readable scheduled_delivery_at timestamp.
   */
  protected function scheduledDeliveryAtHuman(): Attribute
  {
    return Attribute::get(fn() => $this->scheduled_delivery_at?->diffForHumans());
  }

  /**
   * Get the formatted scheduled_delivery_at timestamp.
   */
  protected function scheduledDeliveryAtFormatted(): Attribute
  {
    return Attribute::get(fn() => $this->scheduled_delivery_at?->format('F j, Y H:i:s'));
  }

  /**
   * Get the human-readable actual_delivery_at timestamp.
   */
  protected function actualDeliveryAtHuman(): Attribute
  {
    return Attribute::get(fn() => $this->actual_delivery_at?->diffForHumans());
  }

  /**
   * Get the formatted actual_delivery_at timestamp.
   */
  protected function actualDeliveryAtFormatted(): Attribute
  {
    return Attribute::get(fn() => $this->actual_delivery_at?->format('F j, Y H:i:s'));
  }

  protected function cancelledAtHuman(): Attribute
  {
    return Attribute::get(fn() => $this->cancelled_at?->diffForHumans());
  }

  protected function cancelledAtFormatted(): Attribute
  {
    return Attribute::get(fn() => $this->cancelled_at?->format('F j, Y H:i:s'));
  }

  public function client(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function courier(): BelongsTo
  {
    return $this->belongsTo(User::class, 'courier_id');
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by');
  }

  public function canceller(): BelongsTo
  {
    return $this->belongsTo(User::class, 'cancelled_by');
  }

  public function items(): HasMany
  {
    return $this->hasMany(OrderItem::class);
  }

  public function returnedMaterials(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
  {
    return $this->belongsToMany(RawMaterial::class, 'order_returned_materials')
        ->withPivot('quantity')
        ->withTimestamps();
  }

  /**
   * The original order this row was spun off from when a partial delivery
   * was rolled forward into a backorder.
   */
  public function parentOrder(): BelongsTo
  {
    return $this->belongsTo(Order::class, 'parent_order_id');
  }

  /**
   * Child orders created from this order's undelivered shortfall.
   */
  public function backorders(): HasMany
  {
    return $this->hasMany(Order::class, 'parent_order_id');
  }
}
