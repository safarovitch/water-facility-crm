<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function adminDeposit(Request $request, User $user, \App\Services\WalletService $walletService)
    {
        // TODO: Add proper permission check here once role-based access is fully set up for this specific action
        // For now, we assume anyone hitting this route (admin area) has the right.
        
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $walletService->deposit($user, $request->amount, null, null, [
            'method' => 'admin_deposit',
            'admin_id' => auth()->id(),
            'notes' => $request->notes ?? 'Admin deposit'
        ]);

        return back()->with('success', "Successfully added " . $request->amount . " to {$user->name}'s wallet.");
    }
}
