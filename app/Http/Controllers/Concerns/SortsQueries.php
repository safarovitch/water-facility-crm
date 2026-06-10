<?php

namespace App\Http\Controllers\Concerns;

use Closure;

trait SortsQueries
{
    /**
     * Apply a whitelisted sort from the request's `sort_by` / `sort_dir`.
     *
     * $map keys are the column identifiers the frontend sends; values are
     * either a real column name (string → orderBy) or a Closure(query, dir)
     * for relation / computed sorts. Anything not present in the map is
     * ignored, so a request can never order by an arbitrary column.
     *
     * Returns true when a sort was applied, letting the caller fall back to
     * its own default ordering otherwise.
     */
    protected function applySort($query, array $map): bool
    {
        $by = request('sort_by');

        if (! is_string($by) || ! array_key_exists($by, $map)) {
            return false;
        }

        $dir = strtolower((string) request('sort_dir')) === 'asc' ? 'asc' : 'desc';
        $target = $map[$by];

        if ($target instanceof Closure) {
            $target($query, $dir);
        } else {
            $query->orderBy($target, $dir);
        }

        return true;
    }
}
