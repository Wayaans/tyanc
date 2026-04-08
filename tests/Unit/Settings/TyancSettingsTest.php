<?php

declare(strict_types=1);

use App\Settings\AppearanceSettings;
use App\Settings\AppSettings;
use App\Settings\SecuritySettings;
use App\Settings\UserDefaultsSettings;

it('loads the default tyanc settings from the repository', function (): void {
    expect(resolve(AppSettings::class)->app_name)->toBe('Tyanc')
        ->and(resolve(AppSettings::class)->company_legal_name)->toBe('Tyanc')
        ->and(resolve(AppearanceSettings::class)->spacing_density)->toBe('default')
        ->and(resolve(AppearanceSettings::class)->sidebar_variant)->toBe('inset')
        ->and(resolve(SecuritySettings::class)->session_timeout)->toBe(config('session.lifetime'))
        ->and(resolve(UserDefaultsSettings::class)->appearance)->toBe('system');
});

it('persists updated settings values to the repository', function (): void {
    $settings = resolve(AppearanceSettings::class);
    $settings->primary_color = 'oklch(0.49 0.14 205)';
    $settings->spacing_density = 'compact';
    $settings->font_family = 'instrument-sans';
    $settings->save();

    app()->forgetInstance(AppearanceSettings::class);

    $reloaded = resolve(AppearanceSettings::class);

    expect($reloaded->primary_color)->toBe('oklch(0.49 0.14 205)')
        ->and($reloaded->spacing_density)->toBe('compact')
        ->and($reloaded->font_family)->toBe('instrument-sans');
});
