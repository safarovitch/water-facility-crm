<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
  protected $fillable = [
    'order_number',
    'user_id',
    'status',
    'scheduled_delivery_at',
    'actual_delivery_at',
    'delivery_address',
    'total_amount',
    'paid_amount',
    'payment_status',
    'notes',
    'created_by',
  ];

  protected $casts = [
    'status'                => OrderStatus::class,
    'payment_status'        => PaymentStatus::class,
    'scheduled_delivery_at' => 'datetime',
    'actual_delivery_at'    => 'datetime',
    'total_amount'          => 'decimal:2',
    'paid_amount'           => 'decimal:2',
  ];

  protected $appends = ['balance_due'];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (Order $order) {
      $order->order_number = static::generateOrderNumber();
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

  public function client(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by');
  }

  public function items(): HasMany
  {
    return $this->hasMany(OrderItem::class);
  }
}
