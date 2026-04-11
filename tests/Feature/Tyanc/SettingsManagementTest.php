<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\SettingsAsset;
use App\Models\User;
use App\Models\UserPreference;
use App\Settings\AppearanceSettings;
use App\Settings\AppSettings;
use App\Settings\SecuritySettings;
use App\Settings\UserDefaultsSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function settingsManager(): User
{
    $user = User::factory()->create();

    $permission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('settings', 'manage'),
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    return $user;
}

it('renders the tyanc application settings page for authorized users', function (): void {
    $user = settingsManager();

    $this->actingAs($user)
        ->get(route('tyanc.settings.application.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/settings/Application')
            ->where('settings.app_name', 'Tyanc')
            ->where('settings.company_legal_name', 'Tyanc'));
});

it('updates application settings and stores brand assets with media library', function (): void {
    Storage::fake('public');

    $user = settingsManager();

    $response = $this->actingAs($user)
        ->patchJson(route('tyanc.settings.application.update'), [
            'app_name' => 'Tyanc Admin',
            'company_legal_name' => 'Tyanc Labs LLC',
            'app_logo' => UploadedFile::fake()->image('logo.png', 240, 240),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
            'login_cover_image' => UploadedFile::fake()->image('cover.png', 1200, 630),
        ]);

    $response->assertOk()
        ->assertJsonPath('settings.app_name', 'Tyanc Admin')
        ->assertJsonPath('settings.company_legal_name', 'Tyanc Labs LLC');

    expect($response->json('settings.app_logo'))->toStartWith('/storage/')
        ->and($response->json('settings.favicon'))->toStartWith('/storage/')
        ->and($response->json('settings.login_cover_image'))->toStartWith('/storage/');

    $settings = resolve(AppSettings::class);
    $assetStore = SettingsAsset::forKey(SettingsAsset::GLOBAL_BRANDING_KEY);

    expect($settings->app_name)->toBe('Tyanc Admin')
        ->and($settings->company_legal_name)->toBe('Tyanc Labs LLC')
        ->and($settings->app_logo)->not->toBeNull()
        ->and($settings->favicon)->not->toBeNull()
        ->and($settings->login_cover_image)->not->toBeNull()
        ->and($assetStore->getFirstMedia(SettingsAsset::APP_LOGO_COLLECTION))->not->toBeNull()
        ->and($assetStore->getFirstMedia(SettingsAsset::FAVICON_COLLECTION))->not->toBeNull()
        ->and($assetStore->getFirstMedia(SettingsAsset::LOGIN_COVER_IMAGE_COLLECTION))->not->toBeNull();
});

it('renders and updates appearance settings', function (): void {
    $user = settingsManager();

    $this->actingAs($user)
        ->get(route('tyanc.settings.appearance.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/settings/Appearance')
            ->where('settings.spacing_density', 'default')
            ->where('settings.sidebar_variant', 'inset'));

    $this->actingAs($user)
        ->patchJson(route('tyanc.settings.appearance.update'), [
            'primary_color' => 'oklch(0.45 0.18 210)',
            'secondary_color' => 'oklch(0.94 0 0)',
            'border_radius' => '1rem',
            'spacing_density' => 'comfortable',
            'font_family' => 'instrument-sans',
            'sidebar_variant' => 'floating',
        ])
        ->assertOk()
        ->assertJsonPath('settings.spacing_density', 'comfortable')
        ->assertJsonPath('settings.sidebar_variant', 'floating');

    $settings = resolve(AppearanceSettings::class);

    expect($settings->primary_color)->toBe('oklch(0.45 0.18 210)')
        ->and($settings->border_radius)->toBe('1rem')
        ->and($settings->spacing_density)->toBe('comfortable')
        ->and($settings->font_family)->toBe('instrument-sans')
        ->and($settings->sidebar_variant)->toBe('floating');
});

it('renders and updates security settings', function (): void {
    $user = settingsManager();

    $this->actingAs($user)
        ->get(route('tyanc.settings.security.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/settings/Security')
            ->where('settings.enforce_2fa', false)
            ->where('settings.session_timeout', config('session.lifetime')));

    $this->actingAs($user)
        ->patchJson(route('tyanc.settings.security.update'), [
            'enforce_2fa' => true,
            'session_timeout' => 180,
        ])
        ->assertOk()
        ->assertJsonPath('settings.enforce_2fa', true)
        ->assertJsonPath('settings.session_timeout', 180);

    $settings = resolve(SecuritySettings::class);

    expect($settings->enforce_2fa)->toBeTrue()
        ->and($settings->session_timeout)->toBe(180);
});

it('renders and updates user defaults settings', function (): void {
    $user = settingsManager();

    $this->actingAs($user)
        ->get(route('tyanc.settings.user-defaults.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/settings/UserDefaults')
            ->where('settings.locale', config('app.locale'))
            ->where('settings.timezone', config('app.timezone'))
            ->where('settings.appearance', 'system'));

    $this->actingAs($user)
        ->patchJson(route('tyanc.settings.user-defaults.update'), [
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'appearance' => 'dark',
        ])
        ->assertOk()
        ->assertJsonPath('settings.locale', 'id')
        ->assertJsonPath('settings.timezone', 'Asia/Makassar')
        ->assertJsonPath('settings.appearance', 'dark');

    $settings = resolve(UserDefaultsSettings::class);

    expect($settings->locale)->toBe('id')
        ->and($settings->timezone)->toBe('Asia/Makassar')
        ->and($settings->appearance)->toBe('dark');
});

it('renders and updates personal user preferences', function (): void {
    $user = User::factory()->create([
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    $this->actingAs($user)
        ->get(route('settings.preferences.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Preferences')
            ->where('preferences.resolved_locale', 'en')
            ->where('preferences.resolved_timezone', 'UTC')
            ->where('preferences.resolved_spacing_density', 'default')
            ->where('preferences.resolved_spacing_density_value', 1));

    $this->actingAs($user)
        ->patchJson(route('settings.preferences.update'), [
            'locale' => 'id',
            'timezone' => 'Asia/Makassar',
            'appearance' => 'dark',
            'sidebar_variant' => 'floating',
            'spacing_density' => 'compact',
        ])
        ->assertOk()
        ->assertJsonPath('preferences.locale', 'id')
        ->assertJsonPath('preferences.timezone', 'Asia/Makassar')
        ->assertJsonPath('preferences.resolved_locale', 'id')
        ->assertJsonPath('preferences.resolved_timezone', 'Asia/Makassar')
        ->assertJsonPath('preferences.resolved_appearance', 'dark')
        ->assertJsonPath('preferences.resolved_sidebar_variant', 'floating')
        ->assertJsonPath('preferences.resolved_spacing_density', 'compact');

    $preference = UserPreference::query()->where('user_id', $user->id)->first();

    expect($preference)->not->toBeNull()
        ->and($preference->locale)->toBe('id')
        ->and($preference->timezone)->toBe('Asia/Makassar')
        ->and($preference->appearance)->toBe('dark')
        ->and($preference->sidebar_variant)->toBe('floating')
        ->and($preference->spacing_density)->toBe('compact');
});

it('updates only the user appearance preference without overwriting other preferences', function (): void {
    $user = User::factory()->create();

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
        'timezone' => 'Asia/Makassar',
        'appearance' => 'light',
        'sidebar_variant' => 'floating',
        'spacing_density' => 'compact',
    ]);

    $this->actingAs($user)
        ->patchJson(route('settings.preferences.appearance.update'), [
            'appearance' => 'dark',
        ])
        ->assertOk()
        ->assertJsonPath('appearance', 'dark');

    $preference = UserPreference::query()->where('user_id', $user->id)->first();

    expect($preference)->not->toBeNull()
        ->and($preference->locale)->toBe('id')
        ->and($preference->timezone)->toBe('Asia/Makassar')
        ->and($preference->appearance)->toBe('dark')
        ->and($preference->sidebar_variant)->toBe('floating')
        ->and($preference->spacing_density)->toBe('compact');
});

it('clears user preference overrides when all values are unset', function (): void {
    $user = User::factory()->create();

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
        'timezone' => 'Asia/Jakarta',
        'appearance' => 'dark',
        'sidebar_variant' => 'floating',
        'spacing_density' => 'compact',
    ]);

    $this->actingAs($user)
        ->patchJson(route('settings.preferences.update'), [])
        ->assertOk()
        ->assertJsonPath('preferences.locale', null)
        ->assertJsonPath('preferences.timezone', null)
        ->assertJsonPath('preferences.appearance', null);

    expect(UserPreference::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('forbids tyanc admin settings routes without the tyanc.settings.manage permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tyanc.settings.application.edit'))
        ->assertForbidden();
});
