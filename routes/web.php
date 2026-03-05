<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAddressController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
  Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

  Route::name('users.')->prefix('users')->group(function () {
    Route::get('index', [UserController::class, 'index'])->name('index');
    Route::get('create', [UserController::class, 'create'])->name('create');
    Route::post('store', [UserController::class, 'store'])->name('store');
    Route::get('edit/{user}', [UserController::class, 'edit'])->name('edit');
    Route::post('update/{user}', [UserController::class, 'update'])->name('update');
  });

  Route::name('roles.')->prefix('roles')->group(function () {
    Route::get('index', [UserRoleController::class, 'index'])->name('index');
    Route::get('create', [UserRoleController::class, 'create'])->name('create');
    Route::post('store', [UserRoleController::class, 'store'])->name('store');
    Route::get('edit/{role}', [UserRoleController::class, 'edit'])->name('edit');
    Route::post('update/{role}', [UserRoleController::class, 'update'])->name('update');
  });

  Route::name('permissions.')->prefix('permissions')->group(function () {
    Route::get('index', [UserPermissionController::class, 'index'])->name('index');
    Route::get('create', [UserPermissionController::class, 'create'])->name('create');
    Route::post('store', [UserPermissionController::class, 'store'])->name('store');
    Route::get('edit/{permission}', [UserPermissionController::class, 'edit'])->name('edit');
    Route::post('update/{permission}', [UserPermissionController::class, 'update'])->name('update');
  });

  Route::name('products.')->prefix('products')->group(function () {
    Route::get('index', [ProductController::class, 'index'])->name('index');
    Route::get('create', [ProductController::class, 'create'])->name('create');
    Route::post('store', [ProductController::class, 'store'])->name('store');
    Route::get('edit/{product}', [ProductController::class, 'edit'])->name('edit');
    Route::post('update/{product}', [ProductController::class, 'update'])->name('update');
  });

  Route::name('clients.')->prefix('clients')->group(function () {
    Route::get('index',            [ClientController::class, 'index'])->name('index');
    Route::get('create',           [ClientController::class, 'create'])->name('create');
    Route::post('store',           [ClientController::class, 'store'])->name('store');
    Route::get('edit/{client}',    [ClientController::class, 'edit'])->name('edit');
    Route::post('update/{client}', [ClientController::class, 'update'])->name('update');
    Route::delete('{client}',      [ClientController::class, 'destroy'])->name('destroy');
    // Address sub-routes
    Route::post('{client}/addresses',                       [UserAddressController::class, 'store'])->name('addresses.store');
    Route::post('{client}/addresses/{address}',             [UserAddressController::class, 'update'])->name('addresses.update');
    Route::delete('{client}/addresses/{address}',           [UserAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::patch('{client}/addresses/{address}/default',   [UserAddressController::class, 'setDefault'])->name('addresses.default');
  });

  Route::name('orders.')->prefix('orders')->group(function () {
    Route::get('index',              [OrderController::class, 'index'])->name('index');
    Route::get('create',             [OrderController::class, 'create'])->name('create');
    Route::post('store',             [OrderController::class, 'store'])->name('store');
    Route::get('{order}',            [OrderController::class, 'show'])->name('show');
    Route::get('edit/{order}',       [OrderController::class, 'edit'])->name('edit');
    Route::post('update/{order}',    [OrderController::class, 'update'])->name('update');
    Route::patch('{order}/cancel',   [OrderController::class, 'cancel'])->name('cancel');
    Route::patch('{order}/status',   [OrderController::class, 'updateStatus'])->name('updateStatus');
  });

  Route::name('calls.')->prefix('calls')->group(function () {
    Route::get('/', [\App\Http\Controllers\CallLogController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\CallLogController::class, 'store'])->name('store');
  });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
