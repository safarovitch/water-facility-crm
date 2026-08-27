<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Api\AppAuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserAddressController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

/*
 * Mobile management app API (/api/v1/app).
 *
 * Everything under the `admin` prefix reuses the SAME controllers and the
 * SAME role middleware as routes/web.php — the MobileInertiaBridge
 * middleware turns their Inertia pages and redirects into JSON. Keep the
 * route definitions here in sync with the admin section of web.php: a route
 * added there is invisible to the app until it is mirrored here.
 *
 * Navigation is server-driven: the app builds its menu from /me + /menu
 * (App\Support\MobileMenu), so role/menu changes never require an app
 * release.
 */

// Auth for any staff role (couriers keep their legacy /v1/currier endpoints).
Route::prefix('v1/app')->group(function () {
  Route::post('/auth/login', [AppAuthController::class, 'login'])->middleware('throttle:10,1');
  Route::post('/auth/verify', [AppAuthController::class, 'verify'])->middleware('throttle:10,1');

  Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AppAuthController::class, 'logout']);
    Route::get('/me', [AppAuthController::class, 'me']);
    Route::get('/menu', [AppAuthController::class, 'menu']);

    // Per-user notification inbox (in-app twins of the Telegram alerts).
    Route::get('/notifications', [\App\Http\Controllers\Api\AppNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\AppNotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\AppNotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\AppNotificationController::class, 'markRead']);
  });
});

$adminRoles   = implode('|', \App\Models\User::ADMIN_ROLES);
$managerRoles = $adminRoles . '|Currier manager';
$staffRoles   = $managerRoles . '|Currier';

Route::prefix('v1/app')
  ->middleware([
    \Illuminate\Session\Middleware\StartSession::class,
    'auth:sanctum',
    \App\Http\Middleware\HandleInertiaRequests::class,
    \App\Http\Middleware\MobileInertiaBridge::class,
  ])
  ->group(function () use ($adminRoles, $managerRoles, $staffRoles) {

    // Admin-only sections (mirror of web.php "Admin-only routes group").
    Route::prefix('admin')->middleware("role:{$adminRoles}")->group(function () {
      Route::prefix('users')->group(function () {
        Route::get('index', [UserController::class, 'index']);
        Route::get('create', [UserController::class, 'create']);
        Route::post('store', [UserController::class, 'store']);
        Route::get('show/{user}', [UserController::class, 'show']);
        Route::get('edit/{user}', [UserController::class, 'edit']);
        Route::post('update/{user}', [UserController::class, 'update']);
        Route::get('check-email', [UserController::class, 'checkEmail']);
      });

      Route::prefix('roles')->group(function () {
        Route::get('index', [UserRoleController::class, 'index']);
        Route::get('create', [UserRoleController::class, 'create']);
        Route::post('store', [UserRoleController::class, 'store']);
        Route::get('edit/{role}', [UserRoleController::class, 'edit']);
        Route::post('update/{role}', [UserRoleController::class, 'update']);
      });

      Route::prefix('permissions')->group(function () {
        Route::get('index', [UserPermissionController::class, 'index']);
        Route::get('create', [UserPermissionController::class, 'create']);
        Route::post('store', [UserPermissionController::class, 'store']);
        Route::get('edit/{permission}', [UserPermissionController::class, 'edit']);
        Route::post('update/{permission}', [UserPermissionController::class, 'update']);
      });

      Route::prefix('products')->group(function () {
        Route::get('index', [ProductController::class, 'index']);
        Route::get('create', [ProductController::class, 'create']);
        Route::post('store', [ProductController::class, 'store']);
        Route::get('edit/{product}', [ProductController::class, 'edit']);
        Route::post('update/{product}', [ProductController::class, 'update']);
      });

      Route::prefix('inventory-items')->group(function () {
        Route::get('/', [\App\Http\Controllers\InventoryItemController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\InventoryItemController::class, 'store']);
        Route::post('{inventoryItem}', [\App\Http\Controllers\InventoryItemController::class, 'update']);
        Route::delete('{inventoryItem}', [\App\Http\Controllers\InventoryItemController::class, 'destroy']);
      });

      Route::prefix('raw-materials')->group(function () {
        Route::get('/', [\App\Http\Controllers\RawMaterialController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\RawMaterialController::class, 'store']);
        Route::post('{rawMaterial}', [\App\Http\Controllers\RawMaterialController::class, 'update']);
        Route::delete('{rawMaterial}', [\App\Http\Controllers\RawMaterialController::class, 'destroy']);
      });

      Route::post('users/{user}/wallet/deposit', [\App\Http\Controllers\WalletController::class, 'adminDeposit']);

      Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('create', [SubscriptionController::class, 'create']);
        Route::post('store', [SubscriptionController::class, 'store']);
        Route::get('{subscription}', [SubscriptionController::class, 'show']);
        Route::patch('{subscription}/pause', [SubscriptionController::class, 'pause']);
        Route::patch('{subscription}/resume', [SubscriptionController::class, 'resume']);
        Route::patch('{subscription}/cancel', [SubscriptionController::class, 'cancel']);
      });

      // Call log + click-to-call via the office PBX (these already speak JSON).
      Route::prefix('calls')->group(function () {
        Route::get('/', [\App\Http\Controllers\CallLogController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\CallLogController::class, 'store']);
        Route::post('/originate', [\App\Http\Controllers\CallLogController::class, 'originate']);
      });
    });

    // Self-service settings — any authenticated staff, same controllers as
    // the web settings pages. (2FA and passkeys stay web-only: both are
    // bound to the browser session / WebAuthn.)
    Route::patch('settings/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'update']);
    Route::put('settings/password', [\App\Http\Controllers\Settings\PasswordController::class, 'update'])
      ->middleware('throttle:6,1');

    // Staff-shared sections (mirror of web.php staff group; couriers get the
    // same scoped subset because the controllers apply identical rules —
    // OrderController::isAdminPath() recognises this prefix).
    Route::prefix('admin')->middleware("role:{$staffRoles}")->group(function () use ($adminRoles, $managerRoles) {
      Route::get('dashboard', [AdminDashboardController::class, 'index']);

      Route::prefix('clients')->group(function () use ($adminRoles) {
        Route::get('index',            [ClientController::class, 'index']);
        Route::get('create',           [ClientController::class, 'create']);
        Route::post('store',           [ClientController::class, 'store']);
        Route::get('{client}',         [ClientController::class, 'show'])->whereNumber('client');
        Route::get('edit/{client}',    [ClientController::class, 'edit']);
        Route::post('update/{client}', [ClientController::class, 'update']);
        Route::delete('{client}',      [ClientController::class, 'destroy'])->middleware("role:{$adminRoles}");
        Route::get('{client}/orders',  [ClientController::class, 'orders']);
        Route::post('{client}/transfer-profile', [ClientController::class, 'transferProfile'])->middleware("role:{$adminRoles}");

        Route::post('{client}/addresses',                     [UserAddressController::class, 'store']);
        Route::post('{client}/addresses/{address}',           [UserAddressController::class, 'update']);
        Route::delete('{client}/addresses/{address}',         [UserAddressController::class, 'destroy']);
        Route::patch('{client}/addresses/{address}/default',  [UserAddressController::class, 'setDefault']);
      });

      Route::prefix('orders')->group(function () use ($adminRoles, $managerRoles) {
        Route::get('index',              [OrderController::class, 'index']);
        Route::get('export',             [OrderController::class, 'export']);
        Route::get('assignments',        [OrderController::class, 'assignments'])->middleware("role:{$managerRoles}");
        Route::get('create',             [OrderController::class, 'create'])->middleware("role:{$managerRoles}");
        Route::post('store',             [OrderController::class, 'store'])->middleware("role:{$managerRoles}");
        Route::get('{order}',            [OrderController::class, 'show'])->whereNumber('order');
        Route::post('{order}/repeat',    [OrderController::class, 'repeat'])->middleware("role:{$managerRoles}");
        Route::get('edit/{order}',       [OrderController::class, 'edit'])->middleware("role:{$managerRoles}");
        Route::post('update/{order}',    [OrderController::class, 'update'])->middleware("role:{$managerRoles}");
        Route::post('{order}/refund-overpayment', [OrderController::class, 'refundOverpayment'])->middleware("role:{$adminRoles}");
        Route::patch('{order}/collect-deferred', [OrderController::class, 'collectDeferred']);
        Route::patch('{order}/cancel',   [OrderController::class, 'cancel'])->middleware("role:{$managerRoles}");
        Route::patch('{order}/status',   [OrderController::class, 'updateStatus']);
        Route::post('{order}/pay',       [OrderController::class, 'payWithWallet']);
        Route::post('{order}/pay-from-balance', [OrderController::class, 'payFromWalletBalance']);
        Route::patch('{order}/assign',   [OrderController::class, 'assignCurrier'])->middleware("role:{$managerRoles}");
        Route::delete('{order}',         [OrderController::class, 'destroy'])->middleware("role:{$adminRoles}");
      });

      // Mirrors the forecasts group in web.php. A route added there is
      // invisible to the mobile app until it is mirrored here.
      Route::prefix('forecasts')->group(function () use ($managerRoles, $adminRoles) {
        Route::get('index', [\App\Http\Controllers\ForecastController::class, 'index']);
        Route::post('order', [\App\Http\Controllers\ForecastController::class, 'createOrder'])->middleware("role:{$managerRoles}");

        Route::get('demand', [\App\Http\Controllers\DemandForecastController::class, 'index']);
        Route::get('accuracy', [\App\Http\Controllers\DemandForecastController::class, 'accuracy']);

        Route::get('seasonality', [\App\Http\Controllers\DemandForecastController::class, 'seasonality']);
        Route::post('seasonality', [\App\Http\Controllers\DemandForecastController::class, 'updateSeasonality'])->middleware("role:{$adminRoles}");

        Route::get('segments', [\App\Http\Controllers\DemandForecastController::class, 'segments']);
        Route::post('segments/{user}', [\App\Http\Controllers\DemandForecastController::class, 'updateSegment'])->middleware("role:{$managerRoles}");

        Route::get('routes', [\App\Http\Controllers\RoutePlanController::class, 'index'])->middleware("role:{$managerRoles}");
      });

      Route::prefix('curriers')->middleware("role:{$managerRoles}")->group(function () {
        Route::get('activities', [\App\Http\Controllers\CurrierActivityController::class, 'index']);
      });

      Route::prefix('financial-records')->group(function () use ($adminRoles) {
        Route::get('/', [\App\Http\Controllers\FinancialRecordController::class, 'index']);
        Route::get('export', [\App\Http\Controllers\FinancialRecordController::class, 'export']);
        Route::post('/', [\App\Http\Controllers\FinancialRecordController::class, 'store']);
        Route::post('{financialRecord}', [\App\Http\Controllers\FinancialRecordController::class, 'update']);
        Route::delete('{financialRecord}', [\App\Http\Controllers\FinancialRecordController::class, 'destroy'])->middleware("role:{$adminRoles}");
      });
    });
  });
