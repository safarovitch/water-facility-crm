<?php

namespace App\Models;

use App\Enums\DeliveryTimeSlot;
use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    /**
     * Days in the window this subscription is due to deliver on.
     *
     * Lives on the model because both the demand forecast and the production
     * plan have to answer "what is already committed on Tuesday", and a
     * subscription due next week has no order row yet — only a schedule. Two
     * copies of this arithmetic would eventually disagree, and the production
     * plan would quietly under-fill.
     *
     * @return Carbon[]
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        if (! $this->isActive()) {
            return [];
        }

        $step = $this->intervalDays();

        $cursor = $this->next_delivery_at
            ? $this->next_delivery_at->copy()->startOfDay()
            : $from->copy()->startOfDay();

        // A schedule the generator has not caught up with still has real
        // upcoming dates; roll it forward rather than reporting none.
        $guard = 0;
        while ($cursor->lt($from) && $guard++ < 400) {
            $cursor->addDays($step);
        }

        $dates = [];
        $guard = 0;

        while ($cursor->lte($to) && $guard++ < 400) {
            $dates[] = $cursor->copy();
            $cursor->addDays($step);
        }

        return $dates;
    }

    /**
     * Whole days between deliveries. Monthly is treated as 30 days so the
     * schedule stays a simple repeating step.
     */
    public function intervalDays(): int
    {
        return match ($this->frequency) {
            SubscriptionFrequency::Weekly   => 7,
            SubscriptionFrequency::Biweekly => 14,
            SubscriptionFrequency::Monthly  => 30,
            default                         => max(1, (int) ($this->interval_days ?: 7)),
        };
    }
}
