<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Support\Permissions\PermissionKey;

final readonly class ResolveApprovalCapabilityOptions
{
    public function __construct(private ListApprovalCapabilities $capabilities) {}

    /**
     * @return array{
     *     apps: array<int, array{value: string, label: string}>,
     *     resources: array<string, array<int, array{value: string, label: string}>>,
     *     actions: array<string, array<string, array<int, array{value: string, label: string, permission: string, mode: string}>>>
     * }
     */
    public function handle(): array
    {
        $capabilities = collect($this->capabilities->handle());

        $apps = $capabilities
            ->groupBy(fn (ApprovalCapabilityData $capability): string => $capability->app_key)
            ->map(fn ($group, string $appKey): array => [
                'value' => $appKey,
                'label' => PermissionKey::appLabel($appKey),
            ])
            ->sortBy('label')
            ->values()
            ->all();

        $resources = $capabilities
            ->groupBy(fn (ApprovalCapabilityData $capability): string => $capability->app_key)
            ->map(fn ($group, string $appKey): array => $group
                ->groupBy(fn (ApprovalCapabilityData $capability): string => $capability->resource_key)
                ->map(fn ($resourceGroup, string $resourceKey): array => [
                    'value' => $resourceKey,
                    'label' => PermissionKey::resourceLabel($appKey, $resourceKey),
                ])
                ->sortBy('label')
                ->values()
                ->all())
            ->all();

        $actions = $capabilities
            ->groupBy(fn (ApprovalCapabilityData $capability): string => $capability->app_key)
            ->map(fn ($group): array => $group
                ->groupBy(fn (ApprovalCapabilityData $capability): string => $capability->resource_key)
                ->map(fn ($resourceGroup): array => $resourceGroup
                    ->map(fn (ApprovalCapabilityData $capability): array => [
                        'value' => $capability->action_key,
                        'label' => PermissionKey::actionLabel($capability->action_key),
                        'permission' => $capability->permission_name,
                        'mode' => $capability->mode->value,
                    ])
                    ->sortBy('label')
                    ->values()
                    ->all())
                ->all())
            ->all();

        return [
            'apps' => $apps,
            'resources' => $resources,
            'actions' => $actions,
        ];
    }
}
