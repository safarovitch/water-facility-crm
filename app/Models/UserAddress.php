<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
  protected $fillable = [
    'user_id',
    'label',
    'address_line',
    'city',
    'region',
    'lat',
    'lng',
    'is_default',
  ];

  protected $casts = [
    'lat'        => 'float',
    'lng'        => 'float',
    'is_default' => 'boolean',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }
}
