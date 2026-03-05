<?php

namespace App\Models;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
  protected $fillable = [
    'user_id',
    'type',
    'company_name',
    'region',
    'address',
    'notes',
    'credit_limit',
  ];

  protected $casts = [
    'type'         => ClientType::class,
    'credit_limit' => 'decimal:2',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }
}
