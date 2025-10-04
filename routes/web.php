<?php

use App\Http\Controllers\XeroController;
use App\Http\Controllers\XeroInvoiceController;
use App\Services\XeroService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $xeroService = new XeroService();
    $refreshToken = $xeroService->getSetting('refresh_token');
    $tenants = $xeroService->getConnections();
    return view('welcome', compact('refreshToken', 'tenants'));
})->name('welcome');

Route::group(['prefix' => 'xero', 'as' => 'xero.'], function () {
    Route::get('/callback', [XeroController::class, 'callback'])->name('callback');
    Route::get('/connect', [XeroController::class, 'connect'])->name('connect');

    Route::group(['middleware' => 'is_xero_connected'], function () {
        Route::get('/refresh-tokens', [XeroController::class, 'refreshTokens'])->name('refresh-tokens');
        Route::get('/disconnect', [XeroController::class, 'disconnect'])->name('disconnect');

        Route::group(['prefix' => 'invoices', 'as' => 'invoices.'], function () {
            Route::get('/', [XeroInvoiceController::class, 'index'])->name('index');
        });

        Route::group(['prefix' => 'connections', 'as' => 'connections.'], function () {
            Route::get('/', function () {
                $xeroService = new XeroService();
                $connections = $xeroService->getConnections();
                return view('xero.connections.index', compact('connections'));
            })->name('index');
        });
    });
});
