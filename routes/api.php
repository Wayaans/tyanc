<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccessMatrixController;
use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
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

        Route::middleware('auth')->group(function (): void {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('apps', [AppController::class, 'index'])->name('apps.index');
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
            Route::get('access-matrix', [AccessMatrixController::class, 'index'])->name('access-matrix.index');
            Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        });
    });
