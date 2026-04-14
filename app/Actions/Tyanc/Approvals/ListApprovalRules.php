<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ListApprovalRules
{
    public function __construct(
        private ListApprovalCapabilities $capabilities,
        private ResolveApprovalRuleReadiness $readiness,
    ) {}

    /**
     * @return array<int, array{
     *     id: string|null,
     *     source_key: string,
     *     app_key: string,
     *     app_label: string,
     *     resource_key: string,
     *     resource_label: string,
     *     action_key: string,
     *     action_label: string,
     *     permission_name: string,
     *     mode: string,
     *     enabled: bool,
     *     managed_by_config: bool,
     *     toggleable: bool,
     *     default_enabled: bool,
     *     workflow_type: string,
     *     grant_validity_minutes: int,
     *     reminder_after_minutes: int|null,
     *     escalation_after_minutes: int|null,
     *     step_role_name: string|null,
     *     step_label: string|null,
     *     steps: array<int, array{order: int, role_id: int|null, role_name: string|null, label: string|null}>,
     *     is_ready: bool,
     *     readiness_issues: array<int, string>,
     *     sync_state: string,
     *     retired_at: string|null,
     *     retired_reason: string|null
     * }>
     */
    public function handle(User $actor): array
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'viewany')),
            AuthorizationException::class,
        );

        $capabilities = collect($this->capabilities->handle());
        $runtimeRules = ApprovalRule::query()
            ->with(['steps.role'])
            ->whereIn('permission_name', $capabilities->pluck('permission_name')->all())
            ->get()
            ->keyBy('permission_name');
        $retiredRules = ApprovalRule::query()
            ->with(['steps.role'])
            ->where('managed_by_config', true)
            ->whereNotNull('retired_at')
            ->when(
                $capabilities->isNotEmpty(),
                fn ($query) => $query->whereNotIn('source_key', $capabilities->pluck('source_key')->all()),
            )
            ->get();

        $activeRows = $capabilities
            ->map(fn (ApprovalCapabilityData $capability): array => $this->serializeCapabilityRow(
                $capability,
                $runtimeRules->get($capability->permission_name),
            ))
            ->values();
        $removedRows = $retiredRules
            ->map(fn (ApprovalRule $approvalRule): array => $this->serializeRetiredRuleRow($approvalRule))
            ->values();

        return $activeRows
            ->concat($removedRows)
            ->sortBy(fn (array $row): string => $row['source_key'])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: string|null,
     *     source_key: string,
     *     app_key: string,
     *     app_label: string,
     *     resource_key: string,
     *     resource_label: string,
     *     action_key: string,
     *     action_label: string,
     *     permission_name: string,
     *     mode: string,
     *     enabled: bool,
     *     managed_by_config: bool,
     *     toggleable: bool,
     *     default_enabled: bool,
     *     workflow_type: string,
     *     grant_validity_minutes: int,
     *     reminder_after_minutes: int|null,
     *     escalation_after_minutes: int|null,
     *     step_role_name: string|null,
     *     step_label: string|null,
     *     steps: array<int, array{order: int, role_id: int|null, role_name: string|null, label: string|null}>,
     *     is_ready: bool,
     *     readiness_issues: array<int, string>,
     *     sync_state: string,
     *     retired_at: string|null,
     *     retired_reason: string|null
     * }
     */
    private function serializeCapabilityRow(ApprovalCapabilityData $capability, ?ApprovalRule $runtimeRule): array
    {
        $steps = $runtimeRule instanceof ApprovalRule
            ? $this->serializeRuntimeSteps($runtimeRule)
            : $this->serializeCapabilitySteps($capability);
        $firstStep = $runtimeRule instanceof ApprovalRule
            ? $runtimeRule->steps->sortBy('step_order')->first()
            : null;
        $readiness = $runtimeRule instanceof ApprovalRule
            ? $this->readiness->handle($runtimeRule)
            : [
                'ready' => false,
                'issues' => [__('Sync this capability before configuring workflow settings.')],
            ];

        return [
            'id' => $runtimeRule instanceof ApprovalRule ? (string) $runtimeRule->id : null,
            'source_key' => $capability->source_key,
            'app_key' => $capability->app_key,
            'app_label' => PermissionKey::appLabel($capability->app_key),
            'resource_key' => $capability->resource_key,
            'resource_label' => PermissionKey::resourceLabel($capability->app_key, $capability->resource_key),
            'action_key' => $capability->action_key,
            'action_label' => PermissionKey::actionLabel($capability->action_key),
            'permission_name' => $capability->permission_name,
            'mode' => $capability->mode->value,
            'enabled' => $runtimeRule instanceof ApprovalRule && (bool) $runtimeRule->enabled,
            'managed_by_config' => $runtimeRule instanceof ApprovalRule && (bool) $runtimeRule->managed_by_config,
            'toggleable' => $capability->toggleable,
            'default_enabled' => $capability->default_enabled,
            'workflow_type' => $runtimeRule instanceof ApprovalRule ? $runtimeRule->workflow_type : $capability->workflow_type,
            'grant_validity_minutes' => $runtimeRule instanceof ApprovalRule
                ? $this->grantValidityMinutes($runtimeRule)
                : $capability->grant_validity_minutes,
            'reminder_after_minutes' => $runtimeRule instanceof ApprovalRule
                ? $this->nullableTiming($runtimeRule->reminder_after_minutes)
                : $capability->reminder_after_minutes,
            'escalation_after_minutes' => $runtimeRule instanceof ApprovalRule
                ? $this->nullableTiming($runtimeRule->escalation_after_minutes)
                : $capability->escalation_after_minutes,
            'step_role_name' => $runtimeRule instanceof ApprovalRule
                ? ($firstStep instanceof ApprovalRuleStep ? $firstStep->role->name : null)
                : ($capability->steps[0]['role_name'] ?? null),
            'step_label' => $runtimeRule instanceof ApprovalRule
                ? ($firstStep instanceof ApprovalRuleStep ? $firstStep->label : null)
                : ($capability->steps[0]['label'] ?? null),
            'steps' => $steps,
            'is_ready' => $readiness['ready'],
            'readiness_issues' => $readiness['issues'],
            'sync_state' => $this->syncState($runtimeRule, $readiness['ready']),
            'retired_at' => $runtimeRule?->retired_at?->toIso8601String(),
            'retired_reason' => $runtimeRule?->retired_reason,
        ];
    }

    /**
     * @return array{
     *     id: string|null,
     *     source_key: string,
     *     app_key: string,
     *     app_label: string,
     *     resource_key: string,
     *     resource_label: string,
     *     action_key: string,
     *     action_label: string,
     *     permission_name: string,
     *     mode: string,
     *     enabled: bool,
     *     managed_by_config: bool,
     *     toggleable: bool,
     *     default_enabled: bool,
     *     workflow_type: string,
     *     grant_validity_minutes: int,
     *     reminder_after_minutes: int|null,
     *     escalation_after_minutes: int|null,
     *     step_role_name: string|null,
     *     step_label: string|null,
     *     steps: array<int, array{order: int, role_id: int|null, role_name: string|null, label: string|null}>,
     *     is_ready: bool,
     *     readiness_issues: array<int, string>,
     *     sync_state: string,
     *     retired_at: string|null,
     *     retired_reason: string|null
     * }
     */
    private function serializeRetiredRuleRow(ApprovalRule $approvalRule): array
    {
        $approvalRule->loadMissing(['steps.role']);

        /** @var ApprovalRuleStep|null $firstStep */
        $firstStep = $approvalRule->steps->sortBy('step_order')->first();
        $readinessIssues = [];

        if (is_string($approvalRule->retired_reason) && $approvalRule->retired_reason !== '') {
            $readinessIssues[] = $approvalRule->retired_reason;
        }

        return [
            'id' => (string) $approvalRule->id,
            'source_key' => (string) ($approvalRule->source_key ?? $approvalRule->permission_name),
            'app_key' => (string) $approvalRule->app_key,
            'app_label' => PermissionKey::appLabel((string) $approvalRule->app_key),
            'resource_key' => (string) $approvalRule->resource_key,
            'resource_label' => PermissionKey::resourceLabel((string) $approvalRule->app_key, (string) $approvalRule->resource_key),
            'action_key' => (string) $approvalRule->action_key,
            'action_label' => PermissionKey::actionLabel((string) $approvalRule->action_key),
            'permission_name' => (string) $approvalRule->permission_name,
            'mode' => $approvalRule->mode->value,
            'enabled' => (bool) $approvalRule->enabled,
            'managed_by_config' => true,
            'toggleable' => false,
            'default_enabled' => false,
            'workflow_type' => (string) $approvalRule->workflow_type,
            'grant_validity_minutes' => $this->grantValidityMinutes($approvalRule),
            'reminder_after_minutes' => $this->nullableTiming($approvalRule->reminder_after_minutes),
            'escalation_after_minutes' => $this->nullableTiming($approvalRule->escalation_after_minutes),
            'step_role_name' => $firstStep instanceof ApprovalRuleStep ? $firstStep->role->name : null,
            'step_label' => $firstStep instanceof ApprovalRuleStep ? $firstStep->label : null,
            'steps' => $this->serializeRuntimeSteps($approvalRule),
            'is_ready' => false,
            'readiness_issues' => $readinessIssues,
            'sync_state' => 'removed',
            'retired_at' => $approvalRule->retired_at?->toIso8601String(),
            'retired_reason' => $approvalRule->retired_reason,
        ];
    }

    /**
     * @return array<int, array{order: int, role_id: int|null, role_name: string|null, label: string|null}>
     */
    private function serializeRuntimeSteps(ApprovalRule $approvalRule): array
    {
        return $approvalRule->steps
            ->sortBy('step_order')
            ->map(fn (ApprovalRuleStep $step): array => [
                'order' => $step->step_order,
                'role_id' => $step->role_id,
                'role_name' => $step->role->name,
                'label' => $step->label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{order: int, role_id: int|null, role_name: string|null, label: string|null}>
     */
    private function serializeCapabilitySteps(ApprovalCapabilityData $capability): array
    {
        return collect($capability->steps)
            ->map(fn (array $step): array => [
                'order' => $step['order'],
                'role_id' => null,
                'role_name' => $step['role_name'],
                'label' => $step['label'],
            ])
            ->all();
    }

    private function grantValidityMinutes(ApprovalRule $approvalRule): int
    {
        return max((int) ($approvalRule->grant_validity_minutes ?? 0), 1);
    }

    private function nullableTiming(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function syncState(?ApprovalRule $approvalRule, bool $isReady): string
    {
        if (! $approvalRule instanceof ApprovalRule) {
            return 'pending_sync';
        }

        if ($approvalRule->managed_by_config && $approvalRule->retired_at !== null) {
            return 'removed';
        }

        if ($approvalRule->managed_by_config && ! $isReady) {
            return 'incomplete';
        }

        if ($approvalRule->managed_by_config) {
            return 'synced';
        }

        return 'pending_sync';
    }
}
