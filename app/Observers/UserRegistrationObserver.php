<?php

namespace App\Observers;

use App\Models\User;

class UserRegistrationObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // If the user is created without roles attached, assign the default role as "Client".
        if ($user->roles()->count() === 0) {
            $user->assignRole('Client');
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
