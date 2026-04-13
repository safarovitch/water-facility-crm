<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\CommunicationController;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/currier')->group(function () {
  // Auth
  Route::post('/auth/login', [AuthController::class, 'login']);
  Route::post('/auth/verify', [AuthController::class, 'verify']);
  Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

  // Protected Routes
  Route::middleware(['auth:sanctum', 'role:Currier', \App\Http\Middleware\UpdateCurrierLastActive::class])->group(function () {
    Route::get('/user', function (Request $request) {
      return $request->user();
    });

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/reject', [OrderController::class, 'reject']);
    // Inventory
    Route::get('/reusable-materials', function () {
        return response()->json(
            \App\Models\RawMaterial::where('is_reusable', true)->get(['id', 'name', 'unit'])
        );
    });

    // Profile & Stats
    Route::get('/profile', [ProfileController::class, 'index']);

    // Currier Location tracking
    Route::post('/location', [LocationController::class, 'update']);
    Route::post('/ping', [LocationController::class, 'ping']);

    // Masked Calling
    Route::post('/communication/call', [CommunicationController::class, 'call']);
  });
});
