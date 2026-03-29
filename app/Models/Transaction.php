<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory, HasHumanTimestamps;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'status',
        'reference_id',
        'reference_type',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'meta' => 'array',
    ];

    protected $appends = [
        'created_at_human',
        'created_at_formatted',
        'updated_at_human',
        'updated_at_formatted',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
