<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrierActivityController extends Controller
{
    public function index(): Response
    {
        $curriers = User::role('Currier')
            ->with(['lastLocation', 'orders' => function($q) {
                $q->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
                  ->with('client');
            }])
            ->get();

        return Inertia::render('curriers/Activities', [
            'curriers' => $curriers
        ]);
    }
}
