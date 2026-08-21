<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductionRun extends Model
{
    public const TYPE_PRODUCTION = 'production';
    public const TYPE_COUNT      = 'count';

    protected $fillable = [
        'production_date',
        'product_id',
        'type',
        'units',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'units' => 'integer',
    ];

    /**
     * Stored as a bare Y-m-d string, read back as a Carbon date.
     *
     * The stock ledger is keyed by day and every lookup compares against
     * toDateString(). Laravel's `date` cast writes "2026-08-22 00:00:00" to
     * SQLite, which never equals "2026-08-22", so updateOrCreate could not
     * find the row it had just written and collided with the unique index
     * instead of updating. Normalising on write keeps reads and writes in the
     * same format, and whereBetween on Y-m-d strings still orders correctly.
     */
    protected function productionDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeProduction($query)
    {
        return $query->where('type', self::TYPE_PRODUCTION);
    }

    public function scopeCounts($query)
    {
        return $query->where('type', self::TYPE_COUNT);
    }
}
