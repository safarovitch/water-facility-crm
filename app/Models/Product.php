<?php

namespace App\Models;

use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model implements HasMedia
{
  use InteractsWithMedia, HasSlug, HasHumanTimestamps;

  protected $fillable = [
    'name',
    'sku',
    'slug',
    'short_description',
    'description',
    'price',
    'sale_price',
    'cost',
    'weight',
    'dimensions',
    'currency',
    'quantity',
    'manage_stock',
    'is_produced',
    'low_stock_threshold',
    'low_stock_action',
    'status',
  ];

  protected $casts = [
    'name' => 'array',
    'short_description' => 'array',
    'description' => 'array',
    'dimensions' => 'array',
    'price' => 'decimal:2',
    'sale_price' => 'decimal:2',
    'cost' => 'decimal:2',
    'weight' => 'decimal:2',
    'manage_stock' => 'boolean',
    'is_produced' => 'boolean',
  ];

  protected $appends = [
    'image_url',
    'created_at_human',
    'created_at_formatted',
    'updated_at_human',
    'updated_at_formatted',
  ];

  public function getImageUrlAttribute(): ?string
  {
    return $this->getFirstMediaUrl('image');
  }

  /**
   * Get the options for generating the slug.
   */
  public function getSlugOptions(): SlugOptions
  {
    return SlugOptions::create()
      ->generateSlugsFrom(function (Product $model): string {
        $name = $model->getRawOriginal('name') ?? $model->name;
        if (is_string($name)) {
          $name = json_decode($name, true) ?? [];
        }
        $locales = config('app.available_locales', ['ru', 'tg']);
        foreach ($locales as $loc) {
          if (!empty($name[$loc])) {
            return $name[$loc];
          }
        }
        return array_values((array) $name)[0] ?? 'product';
      })
      ->saveSlugsTo('slug');
  }

  /**
   * Products this business actually manufactures, as opposed to resold side
   * products and reusable containers that merely live in the same catalogue.
   * Drives the daily production plan.
   */
  public function scopeProduced($query)
  {
    return $query->where('is_produced', true);
  }

  public function orderItems(): HasMany
  {
    return $this->hasMany(OrderItem::class);
  }

  public function rawMaterials(): BelongsToMany
  {
    return $this->belongsToMany(RawMaterial::class)
      ->withPivot('quantity')
      ->withTimestamps();
  }
}
