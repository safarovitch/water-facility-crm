<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierLocation extends Model
{
    protected $fillable = [
        'user_id',
        'lat',
        'lng',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
