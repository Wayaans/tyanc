<?php

declare(strict_types=1);

use App\Http\Controllers\Cumpu\ApprovalController;
use App\Http\Controllers\Cumpu\ApprovalRuleController;
use App\Http\Controllers\Cumpu\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

Route::controller(ApprovalController::class)
    ->prefix('approvals')
    ->name('approvals.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('my-requests', 'myRequests')->name('my-requests');
        Route::get('{approvalRequest}', 'show')->name('show');
        Route::patch('{approvalRequest}/approve', 'approve')->name('approve');
        Route::patch('{approvalRequest}/reject', 'reject')->name('reject');
        Route::patch('{approvalRequest}/cancel', 'cancel')->name('cancel');
    });

Route::controller(ApprovalRuleController::class)
    ->prefix('approval-rules')
    ->name('approval-rules.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{approvalRule}', 'update')->name('update');
        Route::delete('{approvalRule}', 'destroy')->name('destroy');
    });
