<?php

namespace App\Models;

use App\Enums\DeliveryTimeSlot;
use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasHumanTimestamps;

    protected $fillable = [
        'user_id',
        'status',
        'frequency',
        'interval_days',
        'day_of_week',
        'day_of_month',
        'time_slot',
        'delivery_address',
        'notes',
        'next_delivery_at',
        'last_generated_at',
        'paused_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'frequency' => SubscriptionFrequency::class,
        'time_slot' => DeliveryTimeSlot::class,
        'next_delivery_at' => 'datetime',
        'last_generated_at' => 'datetime',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'created_at_human',
        'created_at_formatted',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isDue(): bool
    {
        return $this->isActive()
            && $this->next_delivery_at
            && $this->next_delivery_at->isPast();
    }
}
