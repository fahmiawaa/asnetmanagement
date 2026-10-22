<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerMapController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceTaskController;
use App\Http\Controllers\GenieacsController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\InternetPackageController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\IsolationApprovalController;
use App\Http\Controllers\IsolationLogController;
use App\Http\Controllers\MikrotikDeviceController;
use App\Http\Controllers\MikrotikWebhookController;
use App\Http\Controllers\NetworkTopologyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OntDeviceController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OpticalAlertController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PppoeAccountController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:30,1')
    ->name('health');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'role:pelanggan'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/invoices', [CustomerPortalController::class, 'invoices'])
            ->name('invoices.index');

        Route::get('/invoices/{invoice}', [CustomerPortalController::class, 'invoice'])
            ->name('invoices.show');

        Route::get('/payments', [CustomerPortalController::class, 'payments'])
            ->name('payments.index');

        Route::get('/payments/create', [CustomerPortalController::class, 'createPayment'])
            ->name('payments.create');

        Route::post('/payments', [CustomerPortalController::class, 'storePayment'])
            ->name('payments.store');

        Route::get('/payments/{payment}', [CustomerPortalController::class, 'payment'])
            ->name('payments.show');

        Route::get('/payments/{payment}/proof', [CustomerPortalController::class, 'proof'])
            ->name('payments.proof');
    });

Route::resource('customers', CustomerController::class)
    ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi']);

Route::delete(
    '/customers/{customer}',
    [CustomerController::class, 'destroy']
)
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('customers.destroy');

Route::get('/map', [CustomerMapController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('map.index');

Route::resource('internet-packages', InternetPackageController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'verified', 'role:owner,admin']);

Route::resource('invoices', InvoiceController::class)
    ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'role:owner,admin']);

Route::post(
    '/invoices/{invoice}/cancel',
    [InvoiceController::class, 'cancel']
)
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('invoices.cancel');

Route::resource('payments', PaymentController::class)
    ->only(['index', 'create', 'store', 'show'])
    ->middleware(['auth', 'verified', 'role:owner,admin']);

Route::get(
    '/payments/{payment}/proof',
    [PaymentController::class, 'proof']
)
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('payments.proof');

Route::post(
    '/payments/{payment}/reject',
    [PaymentController::class, 'reject']
)
    ->middleware([
        'auth',
        'verified',
        'role:owner,admin',
    ])
    ->name('payments.reject');

Route::post(
    '/payments/{payment}/verify',
    [PaymentController::class, 'verify']
)
    ->middleware([
        'auth',
        'verified',
        'role:owner,admin',
    ])
    ->name('payments.verify');

Route::get(
    '/isolation-logs',
    [IsolationLogController::class, 'index']
)
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('isolation-logs.index');

Route::post(
    '/isolation-logs/{isolationLog}/approve',
    [IsolationApprovalController::class, 'approve']
)
    ->middleware([
        'auth',
        'verified',
        'role:owner,admin',
    ])
    ->name('isolation-logs.approve');

Route::post(
    '/isolation-logs/{isolationLog}/reject',
    [IsolationApprovalController::class, 'reject']
)
    ->middleware([
        'auth',
        'verified',
        'role:owner,admin',
    ])
    ->name('isolation-logs.reject');

Route::resource('mikrotik-devices', MikrotikDeviceController::class)
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'role:owner,teknisi']);

Route::delete(
    '/mikrotik-devices/{mikrotik_device}',
    [MikrotikDeviceController::class, 'destroy']
)
    ->middleware(['auth', 'verified', 'role:owner'])
    ->name('mikrotik-devices.destroy');

Route::get(
    '/pppoe-accounts',
    [PppoeAccountController::class, 'index']
)
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('pppoe-accounts.index');

Route::post(
    '/pppoe-accounts/sync',
    [PppoeAccountController::class, 'sync']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.sync');

Route::get(
    '/pppoe-accounts/active-sessions',
    [PppoeAccountController::class, 'activeSessions']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.sessions');

Route::post(
    '/pppoe-accounts/{pppoeAccount}/provision',
    [PppoeAccountController::class, 'provision']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.provision');

Route::get(
    '/pppoe-accounts/{pppoeAccount}/edit',
    [PppoeAccountController::class, 'edit']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.edit');

Route::put(
    '/pppoe-accounts/{pppoeAccount}',
    [PppoeAccountController::class, 'update']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.update');

Route::post(
    '/pppoe-accounts/{pppoeAccount}/disable',
    [PppoeAccountController::class, 'disable']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.disable');

Route::post(
    '/pppoe-accounts/{pppoeAccount}/enable',
    [PppoeAccountController::class, 'enable']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('pppoe-accounts.enable');

Route::get('/ont-devices', [OntDeviceController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('ont-devices.index');

Route::get('/ont-devices/{ontDevice}', [OntDeviceController::class, 'show'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('ont-devices.show');

Route::patch('/ont-devices/{ontDevice}/mapping', [OntDeviceController::class, 'updateMapping'])
    ->middleware(['auth', 'verified', 'role:owner,teknisi', 'throttle:30,1'])
    ->name('ont-devices.mapping.update');

Route::delete('/ont-devices/{ontDevice}/mapping', [OntDeviceController::class, 'destroyMapping'])
    ->middleware(['auth', 'verified', 'role:owner,teknisi', 'throttle:30,1'])
    ->name('ont-devices.mapping.destroy');

Route::middleware(['auth', 'verified', 'role:owner,teknisi', 'throttle:30,1'])
    ->prefix('ont-devices/{ontDevice}/tasks')
    ->name('ont-devices.tasks.')
    ->group(function () {
        Route::post('/summon', [DeviceTaskController::class, 'summon'])->name('summon');
        Route::post('/reboot', [DeviceTaskController::class, 'reboot'])->name('reboot');
        Route::post('/wifi', [DeviceTaskController::class, 'wifi'])->name('wifi');
    });

Route::get('/device-tasks', [DeviceTaskController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('device-tasks.index');

Route::post('/device-tasks/{deviceTask}/cancel', [DeviceTaskController::class, 'cancel'])
    ->middleware(['auth', 'verified', 'role:owner,teknisi', 'throttle:30,1'])
    ->name('device-tasks.cancel');

Route::get('/optical-alerts', [OpticalAlertController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('optical-alerts.index');

Route::get('/genieacs', [GenieacsController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('genieacs.index');

Route::middleware(['auth', 'verified', 'role:owner', 'throttle:30,1'])
    ->prefix('genieacs')
    ->name('genieacs.')
    ->group(function () {
        Route::post('/', [GenieacsController::class, 'store'])->name('store');
        Route::post('/test', [GenieacsController::class, 'test'])->name('test');
        Route::post('/sync', [GenieacsController::class, 'sync'])->name('sync');
    });

Route::get('/network/topology', [NetworkTopologyController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin,teknisi'])
    ->name('network.topology');
Route::middleware(['auth', 'verified', 'role:owner,teknisi', 'throttle:60,1'])
    ->prefix('network')
    ->name('network.')
    ->group(function () {
        Route::post('/nodes', [NetworkTopologyController::class, 'storeNode'])
            ->name('nodes.store');
        Route::patch('/nodes/{node}', [NetworkTopologyController::class, 'updateNode'])
            ->name('nodes.update');
        Route::post('/nodes/{node}/ports', [NetworkTopologyController::class, 'storePort'])
            ->name('nodes.ports.store');
        Route::post('/links', [NetworkTopologyController::class, 'storeLink'])
            ->name('links.store');
        Route::patch('/links/{link}', [NetworkTopologyController::class, 'updateLink'])
            ->name('links.update');
    });

Route::post(
    '/mikrotik/webhook',
    [MikrotikWebhookController::class, 'store']
)
    ->middleware('throttle:60,1')
    ->name('mikrotik.webhook');

Route::post(
    '/mikrotik-devices/{mikrotik_device}/test-connection',
    [MikrotikDeviceController::class, 'testConnection']
)
    ->middleware(['auth', 'verified', 'role:owner,teknisi'])
    ->name('mikrotik-devices.test-connection');

Route::get(
    '/reports',
    [ReportController::class, 'index']
)
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('reports.index');

Route::get(
    '/reports/export-csv',
    [ReportController::class, 'exportCsv']
)
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('reports.export-csv');

Route::get(
    '/audit-logs',
    [AuditLogController::class, 'index']
)
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('audit-logs.index');

Route::get('/operations', [OperationsController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:owner,admin'])
    ->name('operations.index');

Route::middleware(['auth', 'verified', 'role:owner'])
    ->prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('/{user}/toggle', [UserManagementController::class, 'toggle'])->name('toggle');
    });

Route::middleware('auth')->group(function () {
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])
        ->middleware('verified')
        ->name('notifications.feed');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->middleware('verified')
        ->name('notifications.read-all');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->middleware('verified')
        ->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';
