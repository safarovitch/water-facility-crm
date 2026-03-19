<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
  use HasHumanTimestamps;

  protected $fillable = [
    'user_id',
    'type',
    'company_name',
    'region',
    'address',
    'notes',
  ];

  protected $casts = [
    'type'         => ClientType::class,
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
