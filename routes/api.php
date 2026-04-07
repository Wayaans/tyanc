<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->domain(config('tyanc.api_domain'))
    ->prefix(config('tyanc.api_prefix'))
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('status', fn (): JsonResponse => response()->json([
            'app' => config('app.name'),
            'status' => 'ok',
            'version' => 'v1',
        ]))->name('status');
    });
