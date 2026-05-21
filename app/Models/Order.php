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
    'status',
    'scheduled_delivery_at',
    'actual_delivery_at',
    'delivery_address',
    'total_amount',
    'discount_amount',
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
    return (float) $this->total_amount - (float) $this->paid_amount;
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
}
