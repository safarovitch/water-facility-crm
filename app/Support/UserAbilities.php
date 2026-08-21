<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth for UI ability flags. Every flag mirrors a
 * server-side route / controller restriction — hiding an element in a client
 * (web sidebar or mobile menu) is never the only guard.
 *
 * Shared by HandleInertiaRequests (web) and the mobile API (/api/v1/app/me),
 * so both clients always agree on what a role may do.
 */
class UserAbilities
{
    /**
     * @return array<string, bool>
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'accessAdmin'           => $user->isStaff(),
            'viewAdminStats'        => $user->hasAdminAccess(),
            'manageClients'         => $user->isStaff(),
            'deleteClients'         => $user->hasAdminAccess(),
            'viewForecasts'         => $user->isStaff(),
            // Aggregate volume, procurement and accuracy views. Kept to the
            // manager tier: a plain courier must never see company-wide totals.
            'viewDemandForecast'    => $user->hasAdminAccess() || $user->isCurrierManager(),
            'planRoutes'            => $user->hasAdminAccess() || $user->isCurrierManager(),
            'manageSegments'        => $user->hasAdminAccess() || $user->isCurrierManager(),
            // Editing a seasonality curve changes every forecast the business
            // makes, so it stays with the full-admin tier.
            'manageSeasonality'     => $user->hasAdminAccess(),
            'assignCurriers'        => $user->hasAdminAccess() || $user->isCurrierManager(),
            'viewCurrierActivities' => $user->hasAdminAccess() || $user->isCurrierManager(),
            'manageOrders'          => $user->hasAdminAccess() || $user->isCurrierManager(),
            // Recording an order under an earlier date rewrites history for
            // the reports, so it stays with the full-admin tier.
            'backdateOrders'        => $user->hasAdminAccess(),
            'deleteOrders'          => $user->hasAdminAccess(),
            'accessAccounting'      => $user->isStaff(),
            'manageAccounting'      => $user->hasAdminAccess(),
            'manageUsers'           => $user->hasAdminAccess(),
            'manageProducts'        => $user->hasAdminAccess(),
            'manageInventory'       => $user->hasAdminAccess(),
            'manageRawMaterials'    => $user->hasAdminAccess(),
            'manageSubscriptions'   => $user->hasAdminAccess(),
            'accessCalls'           => $user->hasAdminAccess() && ! empty($user->sip_extension),
        ];
    }
}
