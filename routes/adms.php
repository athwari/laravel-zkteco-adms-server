<?php

use Athwari\ZktecoAdms\Http\Controllers\AdmsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ZKTeco ADMS Protocol Routes
|--------------------------------------------------------------------------
|
| These routes implement the five ADMS protocol endpoints used by ZKTeco
| biometric devices to communicate with the server.
|
*/

Route::prefix(config('zkteco-adms.route_prefix', 'iclock'))
    ->middleware(config('zkteco-adms.route_middleware', []))
    ->group(function () {
        // Attendance data, device info, and user query results
        Route::match(['get', 'post'], '/cdata', [AdmsController::class, 'handleCdata'])
            ->name('zkteco.cdata');

        // Device registration & capabilities
        Route::match(['get', 'post'], '/registry', [AdmsController::class, 'handleRegistry'])
            ->name('zkteco.registry');

        // Device polling for pending commands
        Route::get('/getrequest', [AdmsController::class, 'handleGetRequest'])
            ->name('zkteco.getrequest');

        // Command execution confirmations
        Route::post('/devicecmd', [AdmsController::class, 'handleDeviceCmd'])
            ->name('zkteco.devicecmd');

        // JSON device snapshot (opt-in via config)
        Route::get('/inspect', [AdmsController::class, 'handleInspect'])
            ->name('zkteco.inspect');
    });
