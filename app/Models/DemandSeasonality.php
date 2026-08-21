<?php

namespace App\Models;

use App\Enums\ClientSegment;
use Illuminate\Database\Eloquent\Model;

class DemandSeasonality extends Model
{
    protected $table = 'demand_seasonality';

    protected $fillable = [
        'segment',
        'month',
        'index',
        'source',
        'sample_size',
        'observed_index',
    ];

    protected $casts = [
        'segment'        => ClientSegment::class,
        'month'          => 'integer',
        'index'          => 'float',
        'observed_index' => 'float',
        'sample_size'    => 'integer',
    ];

    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }
}
