<?php

declare(strict_types=1);

namespace App\Support\Permissions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PermissionKey
{
    public const string Pattern = '/^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$/';

    public static function make(string $app, string $resource, string $action): string
    {
        return sprintf(
            '%s.%s.%s',
            self::normalizeSegment($app),
            self::normalizeSegment($resource),
            self::normalizeSegment($action),
        );
    }

    public static function tyanc(string $resource, string $action): string
    {
        return self::make('tyanc', $resource, $action);
    }

    public static function cumpu(string $resource, string $action): string
    {
        return self::make('cumpu', $resource, $action);
    }

    public static function isValid(string $permissionName): bool
    {
        return preg_match(self::Pattern, $permissionName) === 1;
    }

    /**
     * @return array{app: string, resource: string, action: string}|null
     */
    public static function parse(string $permissionName): ?array
    {
        if (! self::isValid($permissionName)) {
            return null;
        }

        [$app, $resource, $action] = explode('.', $permissionName, 3);

        return [
            'app' => $app,
            'resource' => $resource,
            'action' => $action,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return collect((array) config('permission-sot.apps', []))
            ->flatMap(function (mixed $appConfig, string $appKey): Collection {
                if (! is_array($appConfig)) {
                    return collect();
                }

                return collect((array) ($appConfig['resources'] ?? []))
                    ->flatMap(function (mixed $resourceConfig, string $resourceKey) use ($appKey): Collection {
                        if (! is_array($resourceConfig)) {
                            return collect();
                        }

                        return collect((array) ($resourceConfig['actions'] ?? []))
                            ->filter(fn (mixed $action): bool => is_string($action) && $action !== '')
                            ->map(fn (string $action): string => self::make($appKey, $resourceKey, $action));
                    });
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function existsInSource(string $permissionName): bool
    {
        return in_array($permissionName, self::all(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function legacyMap(): array
    {
        return collect((array) config('permission-sot.legacy', []))
            ->filter(fn (mixed $normalizedName, mixed $legacyName): bool => is_string($legacyName) && is_string($normalizedName))
            ->all();
    }

    public static function actionLabel(string $action): string
    {
        $label = config(sprintf('permission-sot.actions.%s.label', $action));

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of($action)
            ->replace('_', ' ')
            ->title()
            ->value();
    }

    public static function appLabel(string $app): string
    {
        $label = config(sprintf('permission-sot.apps.%s.label', $app));

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of($app)
            ->replace('_', ' ')
            ->title()
            ->value();
    }

    public static function resourceLabel(string $app, string $resource): string
    {
        $label = config(sprintf('permission-sot.apps.%s.resources.%s.label', $app, $resource));

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of($resource)
            ->replace('_', ' ')
            ->title()
            ->value();
    }

    /**
     * @return list<string>
     */
    public static function actionsFor(string $app, string $resource): array
    {
        return collect((array) config(sprintf('permission-sot.apps.%s.resources.%s.actions', $app, $resource), []))
            ->filter(fn (mixed $action): bool => is_string($action) && $action !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{label: string, actions: list<string>}>
     */
    public static function resourcesFor(string $app): array
    {
        return collect((array) config(sprintf('permission-sot.apps.%s.resources', $app), []))
            ->mapWithKeys(function (mixed $resourceConfig, string $resourceKey) use ($app): array {
                if (! is_array($resourceConfig)) {
                    return [];
                }

                return [
                    $resourceKey => [
                        'label' => self::resourceLabel($app, $resourceKey),
                        'actions' => self::actionsFor($app, $resourceKey),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function manageImpliedActions(): array
    {
        return collect((array) config('permission-sot.manage_implies', []))
            ->filter(fn (mixed $action): bool => is_string($action) && $action !== '')
            ->values()
            ->all();
    }

    public static function actionForAbility(string $ability): ?string
    {
        $action = config(sprintf('permission-sot.policy_abilities.%s', $ability));

        return is_string($action) && $action !== '' ? $action : null;
    }

    /**
     * @return list<string>
     */
    public static function namesForAbility(string $resourceKey, string $ability): array
    {
        $action = self::actionForAbility($ability);

        if ($action === null) {
            return [];
        }

        [$app, $resource] = array_pad(explode('.', $resourceKey, 2), 2, null);

        if (! is_string($app) || ! is_string($resource) || $app === '' || $resource === '') {
            return [];
        }

        $permissionNames = [self::make($app, $resource, $action)];

        if ($action !== 'manage' && in_array($action, self::manageImpliedActions(), true)) {
            $permissionNames[] = self::make($app, $resource, 'manage');
        }

        return array_values(array_unique($permissionNames));
    }

    private static function normalizeSegment(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replace('-', '_')
            ->replace(' ', '_')
            ->trim('_')
            ->value();
    }
}
