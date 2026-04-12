<?php

declare(strict_types=1);

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Demo\DashboardController as DemoDashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$adminPath = mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/');
$cumpuPath = 'cumpu';
$demoPath = mb_trim((string) config('tyanc.demo_path', 'demo'), '/');

Route::get('/', fn () => Inertia::render('Welcome', [
    'canRegister' => Route::has('register'),
]))->name('home');
Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () use ($adminPath, $cumpuPath, $demoPath): void {
    Route::redirect('dashboard', sprintf('/%s/dashboard', $adminPath));

    Route::prefix($adminPath)->group(base_path('routes/tyanc.php'));
    Route::prefix($cumpuPath)->name('cumpu.')->group(base_path('routes/cumpu.php'));

    Route::prefix($demoPath)->name('demo.')->group(function (): void {
        Route::get('dashboard', [DemoDashboardController::class, 'show'])->name('dashboard');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    Route::redirect('settings', '/settings/account');
    Route::get('settings/account', [AccountSettingsController::class, 'edit'])->name('settings.account.edit');
    Route::patch('settings/account', [AccountSettingsController::class, 'update'])->name('settings.account.update');

    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::redirect('settings/appearance', '/settings/preferences')->name('appearance.edit');

    Route::get('settings/preferences', [UserPreferencesController::class, 'edit'])->name('settings.preferences.edit');
    Route::patch('settings/preferences', [UserPreferencesController::class, 'update'])->name('settings.preferences.update');
    Route::patch('settings/preferences/appearance', [UserPreferencesController::class, 'updateAppearance'])->name('settings.preferences.appearance.update');

    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware('guest')->group(function (): void {
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');

    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
