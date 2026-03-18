<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\CommunicationController;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify', [AuthController::class, 'verify']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        
        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

        // Profile & Stats
        Route::get('/profile', [ProfileController::class, 'index']);

        // Courier Location tracking
        Route::post('/courier/location', [LocationController::class, 'update']);

        // Masked Calling
        Route::post('/communication/call', [CommunicationController::class, 'call']);
    });
});
