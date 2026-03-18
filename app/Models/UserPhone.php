<?php

namespace App\Models;

use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhone extends Model
{
  use HasHumanTimestamps;

  protected $fillable = [
    'user_id',
    'label',
    'phone',
    'is_default',
  ];

  protected $casts = [
    'is_default' => 'boolean',
  ];

  protected $appends = [
    'created_at_human',
    'created_at_formatted',
    'updated_at_human',
    'updated_at_formatted',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }
}
