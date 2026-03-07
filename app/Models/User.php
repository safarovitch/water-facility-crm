<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
  /** @use HasFactory<\Database\Factories\UserFactory> */
  use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

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
    'sip_password'
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

  protected $appends = ['avatar_url', 'statusLabel', 'statusHtmlClass', 'phone'];

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
      'status' => UserStatus::class
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
}
