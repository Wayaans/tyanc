<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRule;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Arr;
use RuntimeException;

final readonly class ListApprovalCapabilities
{
    /**
     * @return array<int, ApprovalCapabilityData>
     */
    public function handle(): array
    {
        $configuredApps = config('approval-sot.apps', []);
        $configuredConditionKeys = config('approval-sot.allowed_condition_keys', []);
        $allowedConditionKeys = collect(is_array($configuredConditionKeys) ? $configuredConditionKeys : [])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        if (! is_array($configuredApps)) {
            throw new RuntimeException(__('The approval capability source is invalid.'));
        }

        $capabilities = [];
        $seenPermissions = [];
        $seenSources = [];

        foreach ($configuredApps as $appKey => $appConfig) {
            if (! is_string($appKey) || ! is_array($appConfig)) {
                throw new RuntimeException(__('Invalid approval app configuration for :app.', ['app' => (string) $appKey]));
            }

            foreach ((array) ($appConfig['resources'] ?? []) as $resourceKey => $resourceConfig) {
                if (! is_string($resourceKey) || ! is_array($resourceConfig)) {
                    throw new RuntimeException(__('Invalid approval resource configuration for :resource.', ['resource' => (string) $resourceKey]));
                }

                foreach ((array) ($resourceConfig['actions'] ?? []) as $actionKey => $actionConfig) {
                    if (! is_string($actionKey) || ! is_array($actionConfig)) {
                        throw new RuntimeException(__('Invalid approval action configuration for :action.', ['action' => (string) $actionKey]));
                    }

                    $permissionName = PermissionKey::make($appKey, $resourceKey, $actionKey);
                    $sourceKey = $permissionName;

                    if (! PermissionKey::existsInSource($permissionName)) {
                        throw new RuntimeException(__('Approval capability :permission must map to a permission defined in config/permission-sot.php.', [
                            'permission' => $permissionName,
                        ]));
                    }

                    if (in_array($permissionName, $seenPermissions, true)) {
                        throw new RuntimeException(__('Duplicate approval capability detected for :permission.', [
                            'permission' => $permissionName,
                        ]));
                    }

                    if (in_array($sourceKey, $seenSources, true)) {
                        throw new RuntimeException(__('Duplicate approval capability source key detected for :source.', [
                            'source' => $sourceKey,
                        ]));
                    }

                    $modeValue = $this->requiredString($actionConfig, 'mode', $permissionName);
                    $mode = ApprovalMode::tryFrom($modeValue);

                    if (! $mode instanceof ApprovalMode || $mode === ApprovalMode::None) {
                        throw new RuntimeException(__('Approval capability :permission uses unsupported mode :mode.', [
                            'permission' => $permissionName,
                            'mode' => $modeValue,
                        ]));
                    }

                    $workflowType = $this->nullableString($actionConfig['workflow_type'] ?? null) ?? ApprovalRule::WorkflowSingle;

                    if (! in_array($workflowType, [ApprovalRule::WorkflowSingle, ApprovalRule::WorkflowMulti], true)) {
                        throw new RuntimeException(__('Approval capability :permission uses unsupported workflow type :workflow.', [
                            'permission' => $permissionName,
                            'workflow' => $workflowType,
                        ]));
                    }

                    $steps = $this->normalizeSteps($permissionName, $workflowType, $actionConfig);
                    $conditions = $this->normalizeConditions($permissionName, $actionConfig, $allowedConditionKeys);
                    $grantValidityMinutes = $this->nullableInt($actionConfig, 'grant_validity_minutes', $permissionName, min: 1) ?? 1440;
                    $reminderAfterMinutes = $this->nullableInt($actionConfig, 'reminder_after_minutes', $permissionName, min: 1);
                    $escalationAfterMinutes = $this->nullableInt($actionConfig, 'escalation_after_minutes', $permissionName, min: 1);

                    if (
                        $reminderAfterMinutes !== null
                        && $escalationAfterMinutes !== null
                        && $escalationAfterMinutes <= $reminderAfterMinutes
                    ) {
                        throw new RuntimeException(__('Approval capability :permission must escalate after the reminder window.', [
                            'permission' => $permissionName,
                        ]));
                    }

                    $configPayload = [
                        'source_key' => $sourceKey,
                        'permission_name' => $permissionName,
                        'app_key' => $appKey,
                        'resource_key' => $resourceKey,
                        'action_key' => $actionKey,
                        'mode' => $mode->value,
                        'managed' => (bool) ($actionConfig['managed'] ?? false),
                        'toggleable' => (bool) ($actionConfig['toggleable'] ?? false),
                        'default_enabled' => (bool) ($actionConfig['default_enabled'] ?? false),
                    ];

                    $capabilities[] = new ApprovalCapabilityData(
                        source_key: $sourceKey,
                        permission_name: $permissionName,
                        app_key: $appKey,
                        resource_key: $resourceKey,
                        action_key: $actionKey,
                        mode: $mode,
                        managed: $configPayload['managed'],
                        toggleable: $configPayload['toggleable'],
                        default_enabled: $configPayload['default_enabled'],
                        workflow_type: $workflowType,
                        steps: $steps,
                        grant_validity_minutes: $grantValidityMinutes,
                        reminder_after_minutes: $reminderAfterMinutes,
                        escalation_after_minutes: $escalationAfterMinutes,
                        conditions: $conditions,
                        config_hash: hash('sha256', json_encode($configPayload, JSON_THROW_ON_ERROR)),
                    );

                    $seenPermissions[] = $permissionName;
                    $seenSources[] = $sourceKey;
                }
            }
        }

        return collect($capabilities)
            ->sortBy(fn (ApprovalCapabilityData $capability): string => $capability->source_key)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{order: int, role_name: string, label: string|null}>
     */
    private function normalizeSteps(string $permissionName, string $workflowType, array $config): array
    {
        $rawSteps = $config['steps'] ?? null;

        if ($rawSteps === null) {
            return [];
        }

        if (! is_array($rawSteps)) {
            throw new RuntimeException(__('Approval capability :permission has invalid reviewer steps.', [
                'permission' => $permissionName,
            ]));
        }

        if ($rawSteps === []) {
            return [];
        }

        $steps = collect($rawSteps)
            ->values()
            ->map(function (mixed $step, int $index) use ($permissionName): array {
                if (! is_array($step)) {
                    throw new RuntimeException(__('Approval capability :permission has an invalid reviewer step.', [
                        'permission' => $permissionName,
                    ]));
                }

                $roleName = $this->nullableString(Arr::get($step, 'role'));

                if ($roleName === null) {
                    throw new RuntimeException(__('Approval capability :permission has a reviewer step without a role name.', [
                        'permission' => $permissionName,
                    ]));
                }

                return [
                    'order' => $index + 1,
                    'role_name' => $roleName,
                    'label' => $this->nullableString(Arr::get($step, 'label')),
                ];
            })
            ->all();

        if ($workflowType === ApprovalRule::WorkflowSingle && count($steps) > 1) {
            throw new RuntimeException(__('Approval capability :permission cannot define more than one reviewer step for a single-step workflow.', [
                'permission' => $permissionName,
            ]));
        }

        if ($workflowType === ApprovalRule::WorkflowMulti && count($steps) === 1) {
            throw new RuntimeException(__('Approval capability :permission must define at least two steps for a multi-step workflow when reviewer defaults are provided.', [
                'permission' => $permissionName,
            ]));
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $allowedConditionKeys
     * @return array<string, mixed>|null
     */
    private function normalizeConditions(string $permissionName, array $config, array $allowedConditionKeys): ?array
    {
        $rawConditions = $config['conditions'] ?? null;

        if ($rawConditions === null) {
            return null;
        }

        if (! is_array($rawConditions)) {
            throw new RuntimeException(__('Approval capability :permission has invalid conditions.', [
                'permission' => $permissionName,
            ]));
        }

        $unknownKeys = collect(array_keys($rawConditions))
            ->filter(fn (mixed $key): bool => ! is_string($key) || ! in_array($key, $allowedConditionKeys, true))
            ->values()
            ->all();

        if ($unknownKeys !== []) {
            throw new RuntimeException(__('Approval capability :permission defines orphaned conditions: :keys.', [
                'permission' => $permissionName,
                'keys' => implode(', ', array_map(static fn (mixed $key): string => (string) $key, $unknownKeys)),
            ]));
        }

        $conditions = [];

        foreach ($rawConditions as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $conditions[$key] = match ($key) {
                'requester_min_level', 'requester_max_level', 'target_role_max_level' => $this->requiredScalarInt($permissionName, $key, $value),
                'subject_types' => $this->requiredStringList($permissionName, $key, $value),
                'changed_fields' => $this->requiredStringList($permissionName, $key, $value),
                default => throw new RuntimeException(__('Approval capability :permission defines orphaned conditions: :key.', [
                    'permission' => $permissionName,
                    'key' => $key,
                ])),
            };
        }

        ksort($conditions);

        return $conditions === [] ? null : $conditions;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function requiredString(array $config, string $key, string $permissionName): string
    {
        $value = $this->nullableString($config[$key] ?? null);

        if ($value === null) {
            throw new RuntimeException(__('Approval capability :permission is missing :key.', [
                'permission' => $permissionName,
                'key' => $key,
            ]));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function nullableInt(array $config, string $key, string $permissionName, int $min = 0): ?int
    {
        $value = $config[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < $min) {
            throw new RuntimeException(__('Approval capability :permission has an invalid :key value.', [
                'permission' => $permissionName,
                'key' => $key,
            ]));
        }

        return (int) $value;
    }

    private function requiredScalarInt(string $permissionName, string $key, mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new RuntimeException(__('Approval capability :permission has an invalid :key value.', [
                'permission' => $permissionName,
                'key' => $key,
            ]));
        }

        return (int) $value;
    }

    /**
     * @return array<int, string>
     */
    private function requiredStringList(string $permissionName, string $key, mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new RuntimeException(__('Approval capability :permission has an invalid :key list.', [
                'permission' => $permissionName,
                'key' => $key,
            ]));
        }

        $values = collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && mb_trim($item) !== '')
            ->map(fn (string $item): string => mb_trim($item))
            ->unique()
            ->values()
            ->all();

        if ($values === []) {
            throw new RuntimeException(__('Approval capability :permission has an invalid :key list.', [
                'permission' => $permissionName,
                'key' => $key,
            ]));
        }

        return $values;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
