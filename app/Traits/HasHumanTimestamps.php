<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasHumanTimestamps
{
    /**
     * Get the human-readable created_at timestamp.
     */
    protected function createdAtHuman(): Attribute
    {
        return Attribute::get(fn () => $this->created_at?->diffForHumans());
    }

    /**
     * Get the formatted created_at timestamp.
     */
    protected function createdAtFormatted(): Attribute
    {
        return Attribute::get(fn () => $this->created_at?->format('F j, Y H:i:s'));
    }

    /**
     * Get the human-readable updated_at timestamp.
     */
    protected function updatedAtHuman(): Attribute
    {
        return Attribute::get(fn () => $this->updated_at?->diffForHumans());
    }

    /**
     * Get the formatted updated_at timestamp.
     */
    protected function updatedAtFormatted(): Attribute
    {
        return Attribute::get(fn () => $this->updated_at?->format('F j, Y H:i:s'));
    }
}
