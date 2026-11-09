<?php

use Illuminate\Support\Facades\Route;
use Nine\Laravel\Server\Http\AdapterController;

/*
|--------------------------------------------------------------------------
| 9in adapter v2 endpoints
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix'     => config('nine-server.route.prefix', 'nine'),
    'middleware' => config('nine-server.route.middleware', []),
], function () {
    Route::post('authenticate', AdapterController::class)->name('nine.adapter.authenticate');
    Route::post('user', AdapterController::class)->name('nine.adapter.user');
    Route::post('event', AdapterController::class)->name('nine.adapter.event');
});
