<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }
        return Inertia::render('Welcome');
    }
}
