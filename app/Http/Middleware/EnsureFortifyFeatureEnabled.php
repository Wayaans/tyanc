<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Notifications\FlashToast;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFortifyFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->isEnabled($feature)) {
            return $next($request);
        }

        return $this->disabledResponse($request, $feature);
    }

    private function isEnabled(string $feature): bool
    {
        return match ($feature) {
            'reset-passwords' => Features::enabled(Features::resetPasswords()),
            'email-verification' => Features::enabled(Features::emailVerification()),
            default => true,
        };
    }

    private function disabledResponse(Request $request, string $feature): RedirectResponse
    {
        $response = $feature === 'email-verification' && $request->isMethod('GET')
            ? to_route('verification.notice')
            : back();

        return $response->with('toast', FlashToast::warning(match ($feature) {
            'reset-passwords' => __('Password reset is unavailable.'),
            'email-verification' => __('Email verification is unavailable.'),
            default => __('Feature is unavailable.'),
        })->toArray());
    }
}
