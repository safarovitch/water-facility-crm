<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class HomeController extends Controller
{
    public function qr()
    {
        return Inertia::render('Qr');
    }
}
