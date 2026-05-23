<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminModeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ClientProfileController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAddressController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Stash a same-origin return URL as the intended-URL, then redirect to /login.
// Used by landing-page CTAs that need the user back on a specific page after login.
Route::get('/auth/start', function (\Illuminate\Http\Request $request) {
    $return = (string) $request->query('return', '/');
    if (! str_starts_with($return, '/')) {
        $return = '/';
    }
    $request->session()->put('url.intended', $return);
    return redirect()->route('login');
})->name('auth.start');

// Custom passkey routes (replaces Route::passkeys()) to fix Session::flash() issue
Route::prefix('passkeys')->group(function () {
    Route::get('authentication-options', [\App\Http\Controllers\Auth\PasskeyLoginController::class, 'authenticationOptions'])
        ->name('passkeys.authentication_options');
    Route::post('authenticate', [\App\Http\Controllers\Auth\PasskeyLoginController::class, 'authenticate'])
        ->name('passkeys.login');
});

Route::middleware(['auth', 'verified'])->group(function () {
  // /profile is the default landing for all users (named 'dashboard' so auth redirects still work)
  Route::get('profile', [DashboardController::class, 'index'])->name('dashboard');
  // /dashboard redirects to /profile for backward compatibility
  Route::redirect('dashboard', '/profile');
  
  // Client profile edit routes
  Route::get('profile/edit', [ClientProfileController::class, 'edit'])->name('client-profile.edit');
  Route::post('profile/edit', [ClientProfileController::class, 'update'])->name('client-profile.update');

  // /admin is the admin-only statistics dashboard
  Route::get('admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
  // Toggle admin mode on/off
  Route::post('admin/switch-mode', [AdminModeController::class, 'switch'])->name('admin.switch-mode');

  // Admin routes group
  Route::prefix('admin')->name('admin.')->group(function () {
    Route::name('users.')->prefix('users')->group(function () {
      Route::get('index', [UserController::class, 'index'])->name('index');
      Route::get('create', [UserController::class, 'create'])->name('create');
      Route::post('store', [UserController::class, 'store'])->name('store');
      Route::get('show/{user}', [UserController::class, 'show'])->name('show');
      Route::get('edit/{user}', [UserController::class, 'edit'])->name('edit');
      Route::post('update/{user}', [UserController::class, 'update'])->name('update');
      Route::get('check-email', [UserController::class, 'checkEmail'])->name('check-email');
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
      Route::get('{client}',         [ClientController::class, 'show'])->name('show');
      Route::get('edit/{client}',    [ClientController::class, 'edit'])->name('edit');
      Route::post('update/{client}', [ClientController::class, 'update'])->name('update');
      Route::delete('{client}',      [ClientController::class, 'destroy'])->name('destroy');
      Route::get('{client}/orders',  [ClientController::class, 'orders'])->name('orders');
      Route::post('{client}/transfer-profile', [ClientController::class, 'transferProfile'])->name('transfer-profile');

      // Address sub-routes under admin/clients
      Route::post('{client}/addresses',                       [UserAddressController::class, 'store'])->name('addresses.store');
      Route::post('{client}/addresses/{address}',             [UserAddressController::class, 'update'])->name('addresses.update');
      Route::delete('{client}/addresses/{address}',           [UserAddressController::class, 'destroy'])->name('addresses.destroy');
      Route::patch('{client}/addresses/{address}/default',   [UserAddressController::class, 'setDefault'])->name('addresses.default');
    });

    Route::name('orders.')->prefix('orders')->group(function () {
      Route::get('index',              [OrderController::class, 'index'])->name('index');
      Route::get('assignments',        [OrderController::class, 'assignments'])->name('assignments');
      Route::get('create',             [OrderController::class, 'create'])->name('create');
      Route::post('store',             [OrderController::class, 'store'])->name('store');
      Route::get('{order}',            [OrderController::class, 'show'])->name('show');
      Route::get('edit/{order}',       [OrderController::class, 'edit'])->name('edit');
      Route::post('update/{order}',    [OrderController::class, 'update'])->name('update');
      Route::patch('{order}/cancel',   [OrderController::class, 'cancel'])->name('cancel');
      Route::patch('{order}/status',   [OrderController::class, 'updateStatus'])->name('updateStatus');
      Route::post('{order}/pay',       [OrderController::class, 'payWithWallet'])->name('pay');
      Route::patch('{order}/assign',   [OrderController::class, 'assignCurrier'])->name('assign');
      Route::delete('{order}',         [OrderController::class, 'destroy'])->name('destroy');
    });

    Route::name('curriers.')->prefix('curriers')->group(function () {
      Route::get('activities', [\App\Http\Controllers\CurrierActivityController::class, 'index'])->name('activities');
    });

    Route::name('calls.')->prefix('calls')->group(function () {
      Route::get('/', [\App\Http\Controllers\CallLogController::class, 'index'])->name('index');
      Route::post('/', [\App\Http\Controllers\CallLogController::class, 'store'])->name('store');
      Route::post('/originate', [\App\Http\Controllers\CallLogController::class, 'originate'])->name('originate');
    });

    Route::name('financial.')->prefix('financial-records')->group(function () {
      Route::get('/', [\App\Http\Controllers\FinancialRecordController::class, 'index'])->name('index');
      Route::post('/', [\App\Http\Controllers\FinancialRecordController::class, 'store'])->name('store');
      Route::post('{financialRecord}', [\App\Http\Controllers\FinancialRecordController::class, 'update'])->name('update');
      Route::delete('{financialRecord}', [\App\Http\Controllers\FinancialRecordController::class, 'destroy'])->name('destroy');
    });

    Route::name('inventory.')->prefix('inventory-items')->group(function () {
      Route::get('/', [\App\Http\Controllers\InventoryItemController::class, 'index'])->name('index');
      Route::post('/', [\App\Http\Controllers\InventoryItemController::class, 'store'])->name('store');
      Route::post('{inventoryItem}', [\App\Http\Controllers\InventoryItemController::class, 'update'])->name('update');
      Route::delete('{inventoryItem}', [\App\Http\Controllers\InventoryItemController::class, 'destroy'])->name('destroy');
    });

    Route::name('raw_materials.')->prefix('raw-materials')->group(function () {
      Route::get('/', [\App\Http\Controllers\RawMaterialController::class, 'index'])->name('index');
      Route::post('/', [\App\Http\Controllers\RawMaterialController::class, 'store'])->name('store');
      Route::post('{rawMaterial}', [\App\Http\Controllers\RawMaterialController::class, 'update'])->name('update');
      Route::delete('{rawMaterial}', [\App\Http\Controllers\RawMaterialController::class, 'destroy'])->name('destroy');
    });

    Route::post('users/{user}/wallet/deposit', [\App\Http\Controllers\WalletController::class, 'adminDeposit'])->name('wallet.deposit');
  });

  // Client-facing orders (keep original paths but clarify they are for clients if needed)
  Route::name('orders.')->prefix('orders')->group(function () {
      Route::get('index', [OrderController::class, 'index'])->name('index');
      // Static routes must be registered before {order} so they don't get
      // captured by the show wildcard.
      Route::get('create', [OrderController::class, 'clientCreate'])->name('client.create');
      Route::post('store', [OrderController::class, 'clientStore'])->name('client.store');
      Route::get('{order}', [OrderController::class, 'show'])->name('show');
      Route::post('{order}/pay', [OrderController::class, 'payWithWallet'])->name('pay');
  });

  // Live courier tracking for the signed-in client's active order
  Route::get('me/active-tracking', [OrderController::class, 'activeTracking'])
      ->name('me.tracking');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
