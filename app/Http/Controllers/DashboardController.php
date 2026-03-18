<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request) : Response {
        $user = $request->user();
        $userController = app(\App\Http\Controllers\UserController::class);
        
        return Inertia::render('users/Show', $userController->getProfileData($user));
    }
}
