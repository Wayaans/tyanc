<?php

declare(strict_types=1);

use App\Http\Controllers\Cumpu\ApprovalController;
use App\Http\Controllers\Cumpu\ApprovalOverviewController;
use App\Http\Controllers\Cumpu\ApprovalReportController;
use App\Http\Controllers\Cumpu\ApprovalRuleController;
use App\Http\Controllers\Cumpu\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

Route::prefix('approvals')
    ->name('approvals.')
    ->group(function (): void {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::get('my-requests', [ApprovalController::class, 'myRequests'])->name('my-requests');
        Route::get('all', [ApprovalOverviewController::class, 'index'])->name('all');

        Route::prefix('reports')
            ->name('reports.')
            ->controller(ApprovalReportController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('export', 'export')->name('export');
            });

        Route::get('{approvalRequest}', [ApprovalController::class, 'show'])->name('show');
        Route::patch('{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('approve');
        Route::patch('{approvalRequest}/reject', [ApprovalController::class, 'reject'])->name('reject');
        Route::patch('{approvalRequest}/reassign', [ApprovalController::class, 'reassign'])->name('reassign');
        Route::patch('{approvalRequest}/cancel', [ApprovalController::class, 'cancel'])->name('cancel');
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
