<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\UserPreferencesData;
use App\Models\SettingsAsset;
use App\Models\User;
use App\Models\UserPreference;
use App\Settings\AppearanceSettings;
use App\Settings\AppSettings;
use App\Settings\UserDefaultsSettings;
use Illuminate\Http\Request;

final readonly class ResolveRuntimeSettings
{
    public function __construct(
        private AppSettings $appSettings,
        private AppearanceSettings $appearanceSettings,
        private UserDefaultsSettings $userDefaultsSettings,
    ) {}

    /**
     * @return array{
     *     brand: array{app_name: string, company_legal_name: string|null, app_logo: string|null, favicon: string|null, login_cover_image: string|null},
     *     theme: array{appearance: string, primary_color: string, secondary_color: string, border_radius: string, sidebar_variant: string, spacing_density: string, spacing_density_value: float, font_family: string, font_family_stack: string, css_variables: array<string, string>},
     *     preferences: UserPreferencesData
     * }
     */
    public function handle(?User $user, Request $request): array
    {
        $preferences = $user instanceof User
            ? ($user->relationLoaded('preference') ? $user->preference : $user->preference()->first())
            : null;
        $assetStore = SettingsAsset::resolveForKey(SettingsAsset::GLOBAL_BRANDING_KEY);

        $resolvedAppearance = $this->resolveAppearance($request, $preferences);
        $resolvedSidebarVariant = $this->resolveSidebarVariant($preferences?->sidebar_variant);
        $resolvedSpacingDensity = $this->resolveSpacingDensity($preferences?->spacing_density);
        $resolvedFontFamily = $this->resolveFontFamily($this->appearanceSettings->font_family);
        $resolvedLocale = $this->resolveLocale($request, $user, $preferences);
        $resolvedTimezone = $this->resolveTimezone($user, $preferences);

        return [
            'brand' => [
                'app_name' => $this->appSettings->app_name,
                'company_legal_name' => $this->appSettings->company_legal_name,
                'app_logo' => $assetStore->resolveUrl(SettingsAsset::APP_LOGO_COLLECTION),
                'favicon' => $assetStore->resolveUrl(SettingsAsset::FAVICON_COLLECTION),
                'login_cover_image' => $assetStore->resolveUrl(SettingsAsset::LOGIN_COVER_IMAGE_COLLECTION),
            ],
            'theme' => [
                'appearance' => $resolvedAppearance,
                'primary_color' => $this->appearanceSettings->primary_color,
                'secondary_color' => $this->appearanceSettings->secondary_color,
                'border_radius' => $this->appearanceSettings->border_radius,
                'sidebar_variant' => $resolvedSidebarVariant,
                'spacing_density' => $resolvedSpacingDensity['key'],
                'spacing_density_value' => $resolvedSpacingDensity['value'],
                'font_family' => $this->appearanceSettings->font_family,
                'font_family_stack' => $resolvedFontFamily['stack'],
                'css_variables' => [
                    '--primary' => $this->appearanceSettings->primary_color,
                    '--sidebar-primary' => $this->appearanceSettings->primary_color,
                    '--ring' => $this->appearanceSettings->primary_color,
                    '--radius' => $this->appearanceSettings->border_radius,
                    '--sidebar-variant' => $resolvedSidebarVariant,
                    '--spacing-density' => (string) $resolvedSpacingDensity['value'],
                    '--font-sans' => $resolvedFontFamily['stack'],
                ],
            ],
            'preferences' => UserPreferencesData::fromState(
                preferences: $preferences,
                resolvedLocale: $resolvedLocale,
                resolvedTimezone: $resolvedTimezone,
                resolvedAppearance: $resolvedAppearance,
                resolvedSidebarVariant: $resolvedSidebarVariant,
                resolvedSpacingDensity: $resolvedSpacingDensity['key'],
                resolvedSpacingDensityValue: $resolvedSpacingDensity['value'],
            ),
        ];
    }

    private function resolveAppearance(Request $request, ?UserPreference $preferences): string
    {
        $appearanceOptions = array_keys((array) config('tyanc.appearance_options', []));
        $appearance = $preferences?->appearance;

        if (is_string($appearance) && in_array($appearance, $appearanceOptions, true)) {
            return $appearance;
        }

        $cookieAppearance = $request->cookie('appearance');

        if (is_string($cookieAppearance) && in_array($cookieAppearance, $appearanceOptions, true)) {
            return $cookieAppearance;
        }

        if (in_array($this->userDefaultsSettings->appearance, $appearanceOptions, true)) {
            return $this->userDefaultsSettings->appearance;
        }

        return (string) config('tyanc.theme.appearance', 'system');
    }

    private function resolveLocale(Request $request, ?User $user, ?UserPreference $preferences): string
    {
        $supportedLocales = array_keys((array) config('tyanc.supported_locales', []));
        $requestLocale = $request->getLocale();

        if (is_string($preferences?->locale) && in_array($preferences->locale, $supportedLocales, true)) {
            return $preferences->locale;
        }

        if (is_string($user?->locale) && in_array($user->locale, $supportedLocales, true)) {
            return $user->locale;
        }

        if (in_array($requestLocale, $supportedLocales, true)) {
            return $requestLocale;
        }

        if (in_array($this->userDefaultsSettings->locale, $supportedLocales, true)) {
            return $this->userDefaultsSettings->locale;
        }

        return (string) config('app.locale', 'en');
    }

    private function resolveTimezone(?User $user, ?UserPreference $preferences): string
    {
        if (is_string($preferences?->timezone) && $preferences->timezone !== '') {
            return $preferences->timezone;
        }

        if ($user instanceof User) {
            return $user->timezone;
        }

        return $this->userDefaultsSettings->timezone !== ''
            ? $this->userDefaultsSettings->timezone
            : (string) config('app.timezone', 'UTC');
    }

    private function resolveSidebarVariant(?string $sidebarVariant): string
    {
        $sidebarVariants = array_keys((array) config('tyanc.sidebar_variants', []));

        if (is_string($sidebarVariant) && in_array($sidebarVariant, $sidebarVariants, true)) {
            return $sidebarVariant;
        }

        return in_array($this->appearanceSettings->sidebar_variant, $sidebarVariants, true)
            ? $this->appearanceSettings->sidebar_variant
            : (string) config('tyanc.theme.sidebar_variant', 'inset');
    }

    /**
     * @return array{key: string, value: float}
     */
    private function resolveSpacingDensity(?string $spacingDensity): array
    {
        $config = (array) config('tyanc.spacing_densities', []);
        $key = is_string($spacingDensity) && array_key_exists($spacingDensity, $config)
            ? $spacingDensity
            : $this->appearanceSettings->spacing_density;

        if (! array_key_exists($key, $config)) {
            $key = (string) config('tyanc.theme.spacing_density', 'default');
        }

        $density = (array) ($config[$key] ?? ['value' => 1.0]);

        return [
            'key' => $key,
            'value' => (float) ($density['value'] ?? 1.0),
        ];
    }

    /**
     * @return array{label: string, stack: string}
     */
    private function resolveFontFamily(string $fontFamily): array
    {
        $fonts = (array) config('tyanc.font_families', []);
        $resolvedFontFamily = (array) ($fonts[$fontFamily] ?? $fonts['geist'] ?? []);

        return [
            'label' => (string) ($resolvedFontFamily['label'] ?? 'Geist'),
            'stack' => (string) ($resolvedFontFamily['stack'] ?? "'Geist', ui-sans-serif, system-ui, sans-serif"),
        ];
    }
}
