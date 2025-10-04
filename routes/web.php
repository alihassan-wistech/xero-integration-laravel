<?php

use App\Http\Controllers\XeroController;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $refreshToken = Setting::where('key', 'xero_refresh_token')->first()?->value;
    return view('welcome', compact('refreshToken'));
})->name('welcome');

Route::group(['prefix' => 'xero', 'as' => 'xero.'], function () {
    Route::get('/callback', [XeroController::class, 'callback'])->name('callback');
    Route::get('/connect', [XeroController::class, 'connect'])->name('connect');
    Route::get('/refresh-tokens', [XeroController::class, 'refreshTokens'])->name('refresh-tokens');
    Route::get('/disconnect', [XeroController::class, 'disconnect'])->name('disconnect');
});
