<?php

namespace App\Models;

use App\Enums\ClientSegment;
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
    'segment',
    'segment_source',
    'segment_confidence',
    'segment_classified_at',
    'company_name',
    'region',
    'address',
    'notes',
  ];

  protected $casts = [
    'type'                  => ClientType::class,
    'segment'               => ClientSegment::class,
    'segment_confidence'    => 'float',
    'segment_classified_at' => 'datetime',
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

  /**
   * A segment set by a human is authoritative — the rules and AI classifiers
   * check this before touching a row so re-running them is always safe.
   */
  public function segmentIsLocked(): bool
  {
    return $this->segment_source === 'manual';
  }
}
