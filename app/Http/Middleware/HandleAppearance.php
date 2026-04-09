<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Settings\ResolveRuntimeSettings;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final readonly class HandleAppearance
{
    public function __construct(private ResolveRuntimeSettings $runtimeSettings) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $runtimeSettings = $this->resolveRuntimeSettings($request);

        View::share('appearance', $runtimeSettings['theme']['appearance']);
        View::share('themeCssVariables', $runtimeSettings['theme']['css_variables']);
        View::share('brand', $runtimeSettings['brand']);
        View::share('appLocale', $request->getLocale());
        View::share('appTimezone', $runtimeSettings['preferences']->resolved_timezone);

        config(['app.timezone' => $runtimeSettings['preferences']->resolved_timezone]);
        date_default_timezone_set($runtimeSettings['preferences']->resolved_timezone);

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRuntimeSettings(Request $request): array
    {
        /** @var array<string, mixed>|null $runtimeSettings */
        $runtimeSettings = $request->attributes->get('tyanc.runtime_settings');

        if (is_array($runtimeSettings)) {
            return $runtimeSettings;
        }

        $user = $request->user();
        $runtimeSettings = $this->runtimeSettings->handle($user instanceof User ? $user : null, $request);
        $request->attributes->set('tyanc.runtime_settings', $runtimeSettings);

        return $runtimeSettings;
    }
}
