<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model implements HasMedia
{
  use InteractsWithMedia, HasSlug;

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
  ];

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
        return $name['en'] ?? $name['uz'] ?? $name['ru'] ?? array_values((array) $name)[0] ?? 'product';
      })
      ->saveSlugsTo('slug');
  }

  public function orderItems(): HasMany
  {
    return $this->hasMany(OrderItem::class);
  }
}
