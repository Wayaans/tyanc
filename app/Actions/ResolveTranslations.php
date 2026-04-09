<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final readonly class ResolveTranslations
{
    /**
     * @return array<string, string>
     */
    public function handle(string $routeName, string $locale): array
    {
        $translations = $this->loadLocaleCatalog($locale);
        $keys = $this->keysForRoute($routeName);

        if ($keys === []) {
            return $translations;
        }

        return Arr::only($translations, $keys);
    }

    /**
     * @return array<string, string>
     */
    private function loadLocaleCatalog(string $locale): array
    {
        $supportedLocales = array_keys((array) config('tyanc.supported_locales', []));
        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $resolvedLocale = in_array($locale, $supportedLocales, true) ? $locale : $fallbackLocale;

        /** @var array<string, string> $fallbackTranslations */
        $fallbackTranslations = $this->decodeTranslations($fallbackLocale);
        /** @var array<string, string> $localeTranslations */
        $localeTranslations = $resolvedLocale === $fallbackLocale
            ? $fallbackTranslations
            : array_replace($fallbackTranslations, $this->decodeTranslations($resolvedLocale));

        return $localeTranslations;
    }

    /**
     * @return array<string, string>
     */
    private function decodeTranslations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        if (! file_exists($path)) {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        return Collection::make($decoded)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value))
            ->all();
    }

    /**
     * @return list<string>
     */
    private function keysForRoute(string $routeName): array
    {
        $groups = ['common', 'navigation'];

        if (
            in_array($routeName, ['home', 'login', 'login.store', 'register', 'register.store'], true)
            || str_starts_with($routeName, 'password.')
            || str_starts_with($routeName, 'verification.')
            || str_starts_with($routeName, 'two-factor.')
        ) {
            $groups[] = 'auth';
            $groups[] = 'two_factor';
        }

        if (str_starts_with($routeName, 'user-profile.')) {
            $groups[] = 'profile';
        }

        if (str_starts_with($routeName, 'settings.preferences.')) {
            $groups[] = 'preferences';
        }

        if (str_starts_with($routeName, 'tyanc.settings.')) {
            $groups[] = 'settings';
        }

        if ($routeName === '' || $routeName === 'dashboard' || str_starts_with($routeName, 'demo.')) {
            $groups[] = 'shell';
        }

        return array_values(array_unique(array_merge(...array_map(
            fn (string $group): array => $this->groupKeys()[$group] ?? [],
            $groups,
        ))));
    }

    /**
     * @return array<string, list<string>>
     */
    private function groupKeys(): array
    {
        return [
            'common' => [
                'English',
                'Bahasa Indonesia',
                'Light',
                'Dark',
                'System',
                'Compact',
                'Default',
                'Comfortable',
                'Geist',
                'Instrument Sans',
                'System UI',
                'Inset',
                'Sidebar',
                'Floating',
                'Language',
                'Select language',
                'Timezone',
                'Choose language',
                'Switch language',
                'Using system default',
                'Save changes',
                'Saving…',
                'Saved.',
                'Replace',
                'Upload',
                'No image set',
                'Remove :label',
                'Back',
                'Continue',
                'Close',
                'Confirm',
            ],
            'navigation' => [
                'Apps',
                'Admin panel',
                'Sandbox',
                'Dashboard',
                'User',
                'Role & Permission',
                'Role',
                'Permissions',
                'Level',
                'Group',
                'App Settings',
                'Main menu',
                'Signed in to :app',
                'Profile',
                'Password',
                'Two-Factor Auth',
                'Preferences',
                'Log out',
                'Account',
                'Manage your profile and account settings',
                'Application',
                'App Appearance',
                'Security',
                'Defaults for New Users',
                'Configure global application settings and defaults',
            ],
            'auth' => [
                'Welcome back',
                'Sign in to your account to continue',
                'Log in',
                'Email address',
                'Forgot password?',
                'Keep me signed in',
                'Sign in',
                "Don't have an account?",
                'Sign up',
                'Create an account',
                'Enter your details below to create your account',
                'Register',
                'Avatar preview',
                'Photo',
                'Upload profile photo',
                'Profile photo · JPG, PNG or WebP · Max 2 MB',
                'First name',
                'Last name',
                'Username',
                '(optional)',
                'Create account',
                'Choose a strong password',
                'Repeat your password',
                'Jane',
                'Smith',
                'Already have an account?',
                'Forgot your password?',
                "Enter your email address and we'll send you a reset link",
                'Forgot password',
                'Password reset unavailable',
                'Password reset is not enabled on this application. Please contact support if you need help accessing your account.',
                'Back to sign in',
                'Send reset link',
                'Remembered your password?',
                'Verify your email',
                'Check your inbox and click the link we sent to confirm your address.',
                'Email verification',
                'Email verification unavailable',
                'Email verification is not required on this application. You can continue without verifying your email address.',
                'Sign out',
                'A new verification link has been sent to your email address.',
                "Didn't receive the email? Check your spam folder or request a new link below.",
                'Resend verification email',
                'Sign out instead',
                'Reset your password',
                'Enter and confirm your new password below',
                'Reset password',
                'Email',
                'New password',
                'Confirm new password',
                'Set new password',
                'Repeat your new password',
                'Your password',
                'Confirm your identity',
                'This is a protected area. Please re-enter your password to proceed.',
                'Password confirmation is disabled',
                'Password confirmation is not available on this application. Contact your administrator for more information.',
                'Confirm and continue',
            ],
            'profile' => [
                'Profile settings',
                'Profile photo',
                'A photo helps people recognize you',
                'Change profile photo',
                'Change photo',
                'JPG, PNG or WebP · Max 2 MB',
                'Account information',
                'Your sign-in details and preferences',
                'Your email address is unverified.',
                'Resend verification email.',
                'Account status',
                'Select status',
                'Account status is managed by administrators.',
                'Personal details',
                'Your name, contact, and personal information',
                'Phone number',
                'Address',
                'Your mailing or home address',
                'Date of birth',
                'Gender',
                'Select gender',
                'Male',
                'Female',
                'Address line 1',
                'Address line 2',
                'City',
                'State / Province',
                'Country',
                'Postal code',
                'Professional',
                'Professional details',
                'Your work and public profile information',
                'Tell other people where you work and what you do',
                'Company',
                'Company name',
                'Bio',
                'Connect your public profiles',
                'Job title',
                'Short bio',
                'Social links',
                'Add public profile links for your social accounts.',
                'LinkedIn URL',
                'Twitter URL',
                'GitHub URL',
                'Save profile',
                'Update password',
                'Ensure your account is using a long, random password to stay secure',
                'Password settings',
                'Current password',
                'Confirm password',
                'Save password',
            ],
            'preferences' => [
                'Preferences',
                'Personalise your display — these settings override application defaults',
                'Display',
                'Theme, sidebar style, and spacing — overrides system defaults',
                'Language & time',
                'Override your locale and timezone preferences',
                'Theme',
                'Use system default',
                'System default',
                'System default (:value)',
                'Using system default: :value',
                'Using system default: :value (×:density)',
                'Sidebar style',
                'Spacing density',
                'Save preferences',
            ],
            'settings' => [
                'Application settings',
                'Identity',
                'Application name and legal information',
                'App name',
                'My Application',
                'Legal name',
                'Displayed in footers and legal notices.',
                'Assets',
                'Logos and images used across the application',
                'App logo',
                'Favicon',
                'Login cover',
                'ICO, PNG · 32×32 recommended',
                'PNG, JPG · 1200×800 recommended',
                'App Appearance settings',
                'Control the global look and feel of the application',
                'Current settings',
                'A preview of the active appearance configuration',
                'Details',
                'All active appearance values',
                'Primary color',
                'Secondary color',
                'Border radius',
                'Font family',
                'Sidebar style',
                'Edit appearance',
                'Changes apply globally. Users can override via personal preferences.',
                'Colors',
                'Primary',
                'Secondary',
                'Select radius',
                'Select font',
                'Select density',
                'Select style',
                'None',
                'XS — 2px',
                'SM — 4px',
                'MD — 6px',
                'LG — 8px',
                'XL — 12px',
                '2XL — 16px',
                'Security settings',
                'Authentication',
                'Two-factor authentication enforcement for all users',
                'Require two-factor authentication',
                'All users must set up 2FA before accessing the application. Users without 2FA will be redirected to the setup screen on login.',
                'Sessions',
                'Idle session timeout configuration',
                'Session timeout (minutes)',
                'Users are logged out after this many minutes of inactivity. Min 5, max 10080 (1 week).',
                'Locale & time',
                'Starting values applied when new user accounts are created',
                'Default language',
                'Default timezone',
                'Appearance',
                'Starting theme preference for newly created user accounts',
                'Default theme',
                'Select theme',
            ],
            'two_factor' => [
                'Two-factor authentication',
                'Add an extra layer of security to your account',
                'Two-Factor Authentication',
                'Two-factor Authentication',
                'Two-Factor Authentication Settings',
                'Two-factor authentication is disabled',
                'Two-factor authentication (2FA) is not available on this application. When enabled, you would be prompted for a secure code during sign-in. Contact your administrator for more information.',
                'Manage your two-factor authentication settings',
                'When you enable two-factor authentication, you will be prompted for a secure pin during login. Retrieve this pin from any TOTP-compatible app (such as Google Authenticator or Authy) on your phone.',
                'Continue setup',
                'Enable two-factor auth',
                "Two-factor authentication is active. You'll be prompted for a secure code from your authenticator app each time you sign in.",
                'Disable two-factor auth',
                'Use a recovery code',
                'Enter one of your emergency recovery codes to regain access to your account.',
                'use an authenticator code instead',
                'Two-factor verification',
                'Open your authenticator app and enter the 6-digit code for your account.',
                'use a recovery code instead',
                'Verify and sign in',
                'Two-factor authentication (2FA) is not available on this application. Contact your administrator for more information.',
                'Two-factor authentication enabled',
                'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
                'Verify authentication code',
                'Enter the 6-digit code from your authenticator app',
                'Enable two-factor authentication',
                'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
                'or, enter the code manually',
                '2FA recovery codes',
                'Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.',
                'Hide',
                'View',
                'recovery codes',
                'Regenerate codes',
                'Each recovery code can be used once to access your account and will be removed after use. If you need more, click',
                'above.',
            ],
            'shell' => [
                'Dashboard',
                'App Settings',
            ],
        ];
    }
}
