<?php

namespace App\Models;

use App\Enums\FinancialTransactionCategory;
use App\Enums\FinancialTransactionType;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FinancialRecord extends Model implements HasMedia
{
    use HasFactory, HasHumanTimestamps, InteractsWithMedia;

    protected $fillable = [
        'amount',
        'type',
        'category',
        'description',
        'transaction_date',
        'recorded_by_id',
        'recordable_id',
        'recordable_type',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'type'             => FinancialTransactionType::class,
        'category'         => FinancialTransactionCategory::class,
        'transaction_date' => 'datetime',
    ];

    protected $appends = [
        'transaction_date_human',
        'transaction_date_formatted',
        'created_at_human',
        'created_at_formatted',
        'updated_at_human',
        'updated_at_formatted',
        'receipt_url',
    ];

    /**
     * Get the receipt URL from the media library.
     */
    protected function receiptUrl(): Attribute
    {
        return Attribute::get(fn () => $this->getFirstMediaUrl('receipts'));
    }

    /**
     * Get the human-readable transaction_date.
     */
    protected function transactionDateHuman(): Attribute
    {
        return Attribute::get(fn () => $this->transaction_date->diffForHumans());
    }

    /**
     * Get the formatted transaction_date.
     */
    protected function transactionDateFormatted(): Attribute
    {
        return Attribute::get(fn () => $this->transaction_date?->format('F j, Y, H:i'));
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function recordable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
