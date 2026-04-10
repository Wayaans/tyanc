<?php

declare(strict_types=1);

$apps = collect((array) config('permission-sot.apps', []))
    ->mapWithKeys(function (mixed $appConfig, string $appKey): array {
        if (! is_array($appConfig)) {
            return [];
        }

        return [
            $appKey => collect((array) ($appConfig['resources'] ?? []))
                ->mapWithKeys(function (mixed $resourceConfig, string $resourceKey): array {
                    if (! is_array($resourceConfig)) {
                        return [];
                    }

                    return [
                        $resourceKey => (string) ($resourceConfig['label'] ?? $resourceKey),
                    ];
                })
                ->all(),
        ];
    })
    ->all();

$actions = collect((array) config('permission-sot.actions', []))
    ->mapWithKeys(function (mixed $actionConfig, string $action): array {
        if (is_array($actionConfig)) {
            return [$action => (string) ($actionConfig['label'] ?? $action)];
        }

        return [$action => (string) $actionConfig];
    })
    ->all();

return [
    'actions' => $actions,
    'policy_abilities' => (array) config('permission-sot.policy_abilities', []),
    'manage_implies' => (array) config('permission-sot.manage_implies', []),
    'apps' => $apps,
];
