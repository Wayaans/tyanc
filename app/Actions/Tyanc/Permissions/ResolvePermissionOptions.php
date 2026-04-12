<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Permissions;

use App\Models\App;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Collection;

final readonly class ResolvePermissionOptions
{
    /**
     * @return array{
     *     apps: list<array{value: string, label: string}>,
     *     resources: array<string, list<array{value: string, label: string}>>,
     *     actions: array<string, array<string, list<array{value: string, label: string, permission: string}>>>
     * }
     */
    public function handle(bool $includeNavigationResources = true): array
    {
        $registeredApps = App::query()->pluck('label', 'key');
        $configuredApps = collect((array) config('permission-sot.apps', []));

        $apps = $configuredApps
            ->keys()
            ->filter(fn (mixed $appKey): bool => is_string($appKey) && $appKey !== '')
            ->map(fn (string $appKey): array => [
                'value' => $appKey,
                'label' => $registeredApps->get($appKey, PermissionKey::appLabel($appKey)),
            ])
            ->values()
            ->all();

        $resources = $configuredApps
            ->mapWithKeys(function (mixed $appConfig, string $appKey) use ($includeNavigationResources): array {
                if (! is_array($appConfig)) {
                    return [];
                }

                return [
                    $appKey => collect((array) ($appConfig['resources'] ?? []))
                        ->filter(fn (mixed $resourceConfig): bool => $this->shouldIncludeResource($resourceConfig, $includeNavigationResources))
                        ->map(fn (mixed $resourceConfig, string $resourceKey): array => [
                            'value' => $resourceKey,
                            'label' => PermissionKey::resourceLabel($appKey, $resourceKey),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $actions = $configuredApps
            ->mapWithKeys(function (mixed $appConfig, string $appKey) use ($includeNavigationResources): array {
                if (! is_array($appConfig)) {
                    return [];
                }

                $resourceActions = collect((array) ($appConfig['resources'] ?? []))
                    ->filter(fn (mixed $resourceConfig): bool => $this->shouldIncludeResource($resourceConfig, $includeNavigationResources))
                    ->mapWithKeys(fn (mixed $resourceConfig, string $resourceKey): array => [
                        $resourceKey => Collection::make((array) (is_array($resourceConfig) ? ($resourceConfig['actions'] ?? []) : []))
                            ->filter(fn (mixed $action): bool => is_string($action) && $action !== '')
                            ->map(fn (string $action): array => [
                                'value' => $action,
                                'label' => PermissionKey::actionLabel($action),
                                'permission' => PermissionKey::make($appKey, $resourceKey, $action),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->all();

                return [$appKey => $resourceActions];
            })
            ->all();

        return [
            'apps' => $apps,
            'resources' => $resources,
            'actions' => $actions,
        ];
    }

    private function shouldIncludeResource(mixed $resourceConfig, bool $includeNavigationResources): bool
    {
        if (! is_array($resourceConfig)) {
            return false;
        }

        return $includeNavigationResources || ! (bool) ($resourceConfig['navigation_only'] ?? false);
    }
}
