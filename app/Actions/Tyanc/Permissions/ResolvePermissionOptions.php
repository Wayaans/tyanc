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
    public function handle(): array
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
            ->mapWithKeys(function (mixed $appConfig, string $appKey): array {
                if (! is_array($appConfig)) {
                    return [];
                }

                return [
                    $appKey => collect(PermissionKey::resourcesFor($appKey))
                        ->map(fn (array $resource, string $resourceKey): array => [
                            'value' => $resourceKey,
                            'label' => $resource['label'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $actions = $configuredApps
            ->mapWithKeys(function (mixed $appConfig, string $appKey): array {
                if (! is_array($appConfig)) {
                    return [];
                }

                $resourceActions = collect(PermissionKey::resourcesFor($appKey))
                    ->mapWithKeys(fn (array $resource, string $resourceKey): array => [
                        $resourceKey => Collection::make($resource['actions'])
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
}
