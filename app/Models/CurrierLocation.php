<?php

namespace App\Models;

use App\Traits\HasHumanTimestamps;
use Illuminate\Database\Eloquent\Model;

class CurrierLocation extends Model
{
    use HasHumanTimestamps;

    protected $fillable = [
        'user_id',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected $appends = [
        'created_at_human',
        'created_at_formatted',
        'updated_at_human',
        'updated_at_formatted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
