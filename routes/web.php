<?php

declare(strict_types=1);

use App\Http\Controllers\Demo\DashboardController as DemoDashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Tyanc\ActivityLogController;
use App\Http\Controllers\Tyanc\DashboardController as TyancDashboardController;
use App\Http\Controllers\Tyanc\NotificationController;
use App\Http\Controllers\Tyanc\Settings\AppearanceSettingsController;
use App\Http\Controllers\Tyanc\Settings\AppSettingsController;
use App\Http\Controllers\Tyanc\Settings\SecuritySettingsController;
use App\Http\Controllers\Tyanc\Settings\UserDefaultsSettingsController;
use App\Http\Controllers\Tyanc\UserController as TyancUserController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$adminPath = mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/');
$demoPath = mb_trim((string) config('tyanc.demo_path', 'demo'), '/');

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () use ($adminPath, $demoPath): void {
    Route::redirect('dashboard', sprintf('/%s/dashboard', $adminPath));

    Route::prefix($adminPath)->group(function () use ($adminPath): void {
        Route::get('dashboard', [TyancDashboardController::class, 'show'])->name('dashboard');

        Route::prefix('users')->name('tyanc.users.')->group(function (): void {
            Route::get('/', new TyancUserController()->index(...))->name('index');
            Route::get('create', new TyancUserController()->create(...))->name('create');
            Route::post('/', new TyancUserController()->store(...))->name('store');
            Route::get('{user}', new TyancUserController()->show(...))->name('show');
            Route::get('{user}/edit', new TyancUserController()->edit(...))->name('edit');
            Route::patch('{user}', new TyancUserController()->update(...))->name('update');
            Route::patch('{user}/suspend', new TyancUserController()->suspend(...))->name('suspend');
            Route::delete('{user}', new TyancUserController()->destroy(...))->name('destroy');
        });

        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('tyanc.activity-log.index');

        Route::prefix('notifications')->name('tyanc.notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::patch('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
            Route::patch('{notification}', [NotificationController::class, 'update'])->name('update');
        });

        Route::prefix('settings')->name('tyanc.settings.')->group(function () use ($adminPath): void {
            Route::redirect('/', '/'.$adminPath.'/settings/application')->name('index');

            Route::get('application', [AppSettingsController::class, 'edit'])->name('application.edit');
            Route::patch('application', [AppSettingsController::class, 'update'])->name('application.update');

            Route::get('appearance', [AppearanceSettingsController::class, 'edit'])->name('appearance.edit');
            Route::patch('appearance', [AppearanceSettingsController::class, 'update'])->name('appearance.update');

            Route::get('security', [SecuritySettingsController::class, 'edit'])->name('security.edit');
            Route::patch('security', [SecuritySettingsController::class, 'update'])->name('security.update');

            Route::get('user-defaults', [UserDefaultsSettingsController::class, 'edit'])->name('user-defaults.edit');
            Route::patch('user-defaults', [UserDefaultsSettingsController::class, 'update'])->name('user-defaults.update');
        });
    });

    Route::prefix($demoPath)->name('demo.')->group(function (): void {
        Route::get('dashboard', [DemoDashboardController::class, 'show'])->name('dashboard');
    });
});

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::redirect('settings/appearance', '/settings/preferences')->name('appearance.edit');

    // Preferences...
    Route::get('settings/preferences', [UserPreferencesController::class, 'edit'])->name('settings.preferences.edit');
    Route::patch('settings/preferences', [UserPreferencesController::class, 'update'])->name('settings.preferences.update');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware('guest')->group(function (): void {
    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');

    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
