<?php

declare(strict_types=1);

use App\Http\Controllers\Tyanc\AccessMatrixController;
use App\Http\Controllers\Tyanc\ActivityLogController;
use App\Http\Controllers\Tyanc\AppController;
use App\Http\Controllers\Tyanc\ConversationController;
use App\Http\Controllers\Tyanc\DashboardController;
use App\Http\Controllers\Tyanc\ExportController;
use App\Http\Controllers\Tyanc\FileController;
use App\Http\Controllers\Tyanc\ImportController;
use App\Http\Controllers\Tyanc\MessageController;
use App\Http\Controllers\Tyanc\NotificationController;
use App\Http\Controllers\Tyanc\PermissionController;
use App\Http\Controllers\Tyanc\RoleController;
use App\Http\Controllers\Tyanc\Settings\AppearanceSettingsController;
use App\Http\Controllers\Tyanc\Settings\AppSettingsController;
use App\Http\Controllers\Tyanc\Settings\SecuritySettingsController;
use App\Http\Controllers\Tyanc\Settings\UserDefaultsSettingsController;
use App\Http\Controllers\Tyanc\UserController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

Route::controller(AppController::class)
    ->prefix('apps')
    ->name('tyanc.apps.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{app}/edit', 'edit')->name('edit');
        Route::patch('{app}', 'update')->name('update');
        Route::patch('{app}/toggle', 'toggle')->name('toggle');
        Route::delete('{app}', 'destroy')->name('destroy');
    });

Route::controller(UserController::class)
    ->prefix('users')
    ->name('tyanc.users.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{user}', 'show')->name('show');
        Route::get('{user}/edit', 'edit')->name('edit');
        Route::patch('{user}', 'update')->name('update');
        Route::patch('{user}/drafts/submit', 'submitDraft')->name('drafts.submit');
        Route::patch('{user}/drafts/commit', 'commitDraft')->name('drafts.commit');
        Route::patch('{user}/suspend', 'suspend')->name('suspend');
        Route::delete('{user}', 'destroy')->name('destroy');
    });

Route::controller(RoleController::class)
    ->prefix('roles')
    ->name('tyanc.roles.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{role}', 'update')->name('update');
        Route::patch('{role}/permissions', 'assignPermissions')->name('permissions.update');
        Route::delete('{role}', 'destroy')->name('destroy');
    });

Route::controller(PermissionController::class)
    ->prefix('permissions')
    ->name('tyanc.permissions.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('sync', 'sync')->name('sync');
    });

Route::controller(AccessMatrixController::class)
    ->prefix('access-matrix')
    ->name('tyanc.access-matrix.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::patch('/', 'update')->name('update');
    });

Route::controller(FileController::class)
    ->prefix('files')
    ->name('tyanc.files.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{media}', 'destroy')->name('destroy');
    });

Route::controller(ConversationController::class)
    ->prefix('messages')
    ->name('tyanc.messages.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:tyanc-messages')->name('create');
        Route::patch('{conversation}/archive', 'archive')->name('archive');
        Route::delete('{conversation}', 'destroy')->name('destroy');
    });

Route::post('messages/{conversation}', [MessageController::class, 'store'])
    ->middleware('throttle:tyanc-messages')
    ->name('tyanc.messages.store');

Route::controller(ExportController::class)->group(function (): void {
    Route::get('exports/users', 'users')->name('tyanc.users.export');
    Route::get('exports/users/pdf', 'usersPdf')->name('tyanc.users.export.pdf');
    Route::get('exports/activity-log', 'activityLog')->name('tyanc.activity-log.export');
    Route::get('exports/activity-log/pdf', 'activitySummaryPdf')->name('tyanc.activity-log.export.pdf');
});

Route::post('imports/users', [ImportController::class, 'store'])->name('tyanc.users.import.store');

Route::prefix('approvals')
    ->name('tyanc.approvals.')
    ->group(function (): void {
        Route::get('/', fn (): RedirectResponse => to_route('cumpu.approvals.index'))->name('index');
        Route::get('my-requests', fn (): RedirectResponse => to_route('cumpu.approvals.my-requests'))->name('my-requests');
    });

Route::get('activity-log', [ActivityLogController::class, 'index'])->name('tyanc.activity-log.index');

Route::prefix('notifications')
    ->name('tyanc.notifications.')
    ->controller(NotificationController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::patch('mark-all-read', 'markAllRead')->name('mark-all-read');
        Route::patch('{notification}', 'update')->name('update');
    });

Route::prefix('settings')->name('tyanc.settings.')->group(function (): void {
    Route::get('/', fn (): RedirectResponse => to_route('tyanc.settings.application.edit'))->name('index');

    Route::get('application', [AppSettingsController::class, 'edit'])->name('application.edit');
    Route::patch('application', [AppSettingsController::class, 'update'])->name('application.update');

    Route::get('appearance', [AppearanceSettingsController::class, 'edit'])->name('appearance.edit');
    Route::patch('appearance', [AppearanceSettingsController::class, 'update'])->name('appearance.update');

    Route::get('security', [SecuritySettingsController::class, 'edit'])->name('security.edit');
    Route::patch('security', [SecuritySettingsController::class, 'update'])->name('security.update');

    Route::get('user-defaults', [UserDefaultsSettingsController::class, 'edit'])->name('user-defaults.edit');
    Route::patch('user-defaults', [UserDefaultsSettingsController::class, 'update'])->name('user-defaults.update');
});
