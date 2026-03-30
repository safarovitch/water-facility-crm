<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasHumanTimestamps;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class InventoryItem extends Model implements HasMedia
{
    use HasFactory, HasHumanTimestamps, InteractsWithMedia;

    protected $fillable = [
        'name',
        'category',
        'quantity',
        'unit',
        'status',
        'location',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
    ];
    
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('photo');
    }

    protected $appends = [
        'photo_url',
        'created_at_human',
        'created_at_formatted',
    ];

    public function financialRecords(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(FinancialRecord::class, 'recordable');
    }
}
