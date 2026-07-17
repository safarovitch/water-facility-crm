<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Daily per-URL view counter (see TrackPageView middleware).
 */
class PageView extends Model
{
    protected $fillable = ['path', 'date', 'views'];

    protected $casts = ['date' => 'date'];

    /**
     * Bump today's counter for a path. Increment-first keeps the hot path a
     * single UPDATE; the INSERT only happens once per path per day. The
     * duplicate-key catch covers two requests racing on that first view.
     */
    public static function record(string $path): void
    {
        $date = now()->toDateString();

        $affected = DB::table('page_views')
            ->where('path', $path)
            ->where('date', $date)
            ->increment('views');

        if ($affected === 0) {
            try {
                DB::table('page_views')->insert([
                    'path'       => $path,
                    'date'       => $date,
                    'views'      => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException) {
                DB::table('page_views')
                    ->where('path', $path)
                    ->where('date', $date)
                    ->increment('views');
            }
        }
    }
}
