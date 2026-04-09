<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);
        $request->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_keys((array) config('tyanc.supported_locales', []));
        $fallbackLocale = (string) config('app.locale', 'en');
        $user = $request->user();

        if ($user instanceof User) {
            $user->loadMissing('preference');

            $preferredLocale = $user->preference?->locale;

            if (is_string($preferredLocale) && in_array($preferredLocale, $supportedLocales, true)) {
                return $preferredLocale;
            }

            if (is_string($user->locale) && in_array($user->locale, $supportedLocales, true)) {
                return $user->locale;
            }
        }

        $sessionLocale = $request->session()->get('locale');

        if (is_string($sessionLocale) && in_array($sessionLocale, $supportedLocales, true)) {
            return $sessionLocale;
        }

        return in_array($fallbackLocale, $supportedLocales, true)
            ? $fallbackLocale
            : (string) config('app.fallback_locale', 'en');
    }
}
