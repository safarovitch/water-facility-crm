<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserStatus;
use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\CurrierLocation;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;

class User extends Authenticatable implements HasPasskeys
{
  /** @use HasFactory<\Database\Factories\UserFactory> */
  use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles, HasHumanTimestamps, HasApiTokens, \Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

  /**
   * The attributes that are mass assignable.
   *
   * @var list<string>
   */
  protected $fillable = [
    'name',
    'email',
    'password',
    'avatar',
    'status',
    'sip_extension',
    'sip_password',
    'last_active_at',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var list<string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  protected $appends = [
    'avatar_url',
    'statusLabel',
    'statusHtmlClass',
    'phone',
    'last_active_at_human',
    'last_active_at_formatted',
    'created_at_human',
    'created_at_formatted',
    'updated_at_human',
    'updated_at_formatted',
  ];

  /**
   * Get the human-readable last_active_at.
   */
  protected function lastActiveAtHuman(): Attribute
  {
    return Attribute::get(fn() => $this->last_active_at?->diffForHumans());
  }

  /**
   * Get the formatted last_active_at.
   */
  protected function lastActiveAtFormatted(): Attribute
  {
    return Attribute::get(fn() => $this->last_active_at?->format('F j, Y H:i:s'));
  }

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
      'status' => UserStatus::class,
      'last_active_at' => 'datetime',
    ];
  }

  public function getAvatarUrlAttribute(): string
  {
    return Cache::remember('avatar_url' . $this->id, 8600, function () {
      return $this->avatar
        ? asset("storage/{$this->avatar}")
        : "https://ui-avatars.com/api/?background=random&name={$this->name}";
    });
  }

  public function getPhoneAttribute(): ?string
  {
    return $this->phones->where('is_default', true)->first()?->phone
      ?? $this->phones->first()?->phone;
  }

  public function getStatusLabelAttribute(): string
  {
    return $this->status?->key ?? 'unknown';
  }

  public function getStatusHtmlClassAttribute(): string
  {
    $statusCss = [
      'active'   => 'bg-green-500',
      'inactive' => 'bg-gray-400',
      'pending'  => 'bg-[#1b1b18] animate-pulse',
      'blocked'  => 'bg-destructive',
    ];

    return $statusCss[$this->status?->value ?? ''] ?? 'bg-gray-300';
  }

  #[scope]
  public function excludeSelf(Builder $query)
  {
    $query->where('id', '!=', auth()->id());
  }

  public function userProfile(): HasOne
  {
    return $this->hasOne(UserProfile::class);
  }

  public function wallet(): HasOne
  {
    return $this->hasOne(Wallet::class);
  }

  public function orders(): HasMany
  {
    return $this->hasMany(Order::class, 'user_id');
  }

  public function addresses(): HasMany
  {
    return $this->hasMany(UserAddress::class);
  }

  public function phones(): HasMany
  {
    return $this->hasMany(UserPhone::class);
  }

  public function isClient(): bool
  {
    return $this->hasRole('Client');
  }

  public function isCurrier(): bool
  {
    return $this->hasRole('Currier');
  }

  public function locations(): HasMany
  {
      return $this->hasMany(CurrierLocation::class);
  }

  public function lastLocation(): HasOne
  {
      return $this->hasOne(CurrierLocation::class)->latestOfMany();
  }
}
