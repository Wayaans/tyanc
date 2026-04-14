<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class SyncApprovalRulesFromSource
{
    public function __construct(
        private ListApprovalCapabilities $capabilities,
        private AuditLegacyApprovalRules $auditLegacyApprovalRules,
        private SyncApprovalRuleSteps $syncApprovalRuleSteps,
    ) {}

    /**
     * @return array{created: int, updated: int, converted: int, retired: int, checked: int, total: int}
     */
    public function handle(?User $actor = null): array
    {
        if ($actor instanceof User) {
            throw_if(
                ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'manage')),
                AuthorizationException::class,
            );
        }

        $capabilities = $this->capabilities->handle();
        $audit = $this->auditLegacyApprovalRules->handle($capabilities);
        $now = now();

        return DB::transaction(function () use ($actor, $capabilities, $audit, $now): array {
            $created = 0;
            $updated = 0;
            $converted = 0;

            foreach ($capabilities as $capability) {
                $result = $this->syncCapability($capability);
                $created += $result['created'];
                $updated += $result['updated'];
                $converted += $result['converted'];
            }

            $retired = $this->retireMissingManagedRules($capabilities, $now);

            if ($actor instanceof User) {
                activity('approvals')
                    ->causedBy($actor)
                    ->event('rule-sync')
                    ->withProperties([
                        'created' => $created,
                        'updated' => $updated,
                        'converted' => $converted,
                        'retired' => $retired,
                        'checked' => $audit['checked'],
                        'total' => count($capabilities),
                    ])
                    ->log('Approval rules synced from source');
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'converted' => $converted,
                'retired' => $retired,
                'checked' => $audit['checked'],
                'total' => count($capabilities),
            ];
        });
    }

    /**
     * @return array{created: int, updated: int, converted: int}
     */
    private function syncCapability(ApprovalCapabilityData $capability): array
    {
        /** @var ApprovalRule|null $existingRule */
        $existingRule = ApprovalRule::query()
            ->with('steps.role')
            ->where('permission_name', $capability->permission_name)
            ->lockForUpdate()
            ->first();

        $created = 0;
        $updated = 0;
        $converted = 0;

        $approvalRule = $existingRule ?? new ApprovalRule;
        $isNewRule = ! $existingRule instanceof ApprovalRule;
        $enabled = $existingRule instanceof ApprovalRule
            ? (bool) $existingRule->enabled
            : ($capability->default_enabled && $this->seededRuntimeConfigurationIsReady($capability));
        $wasManaged = $existingRule instanceof ApprovalRule && (bool) $existingRule->managed_by_config;

        $approvalRule->forceFill([
            'app_key' => $capability->app_key,
            'resource_key' => $capability->resource_key,
            'action_key' => $capability->action_key,
            'permission_name' => $capability->permission_name,
            'enabled' => $enabled,
            'mode' => $capability->mode->value,
            'managed_by_config' => $capability->managed,
            'source_key' => $capability->source_key,
            'config_hash' => $capability->config_hash,
            'retired_at' => null,
            'retired_reason' => null,
        ]);

        if ($isNewRule) {
            $approvalRule->forceFill([
                'workflow_type' => $capability->workflow_type,
                'conditions' => $capability->conditions,
                'grant_validity_minutes' => $capability->grant_validity_minutes,
                'reminder_after_minutes' => $capability->reminder_after_minutes,
                'escalation_after_minutes' => $capability->escalation_after_minutes,
            ]);
        }

        $approvalRule->save();

        if ($isNewRule && $capability->steps !== []) {
            /** @var list<array{role_name: string, label: string|null}> $steps */
            $steps = collect($capability->steps)
                ->map(fn (array $step): array => [
                    'role_name' => $step['role_name'],
                    'label' => $step['label'],
                ])
                ->values()
                ->all();

            $this->syncApprovalRuleSteps->handle($approvalRule, $steps);
        }

        if (! $existingRule instanceof ApprovalRule) {
            $created++;
        } elseif (! $wasManaged) {
            $converted++;
        } else {
            $updated++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'converted' => $converted,
        ];
    }

    private function seededRuntimeConfigurationIsReady(ApprovalCapabilityData $capability): bool
    {
        if ($capability->steps === []) {
            return false;
        }

        if ($capability->workflow_type === ApprovalRule::WorkflowMulti && count($capability->steps) < 2) {
            return false;
        }

        if ($capability->grant_validity_minutes < 1) {
            return false;
        }

        if (
            $capability->reminder_after_minutes !== null
            && $capability->escalation_after_minutes !== null
            && $capability->escalation_after_minutes <= $capability->reminder_after_minutes
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, ApprovalCapabilityData>  $capabilities
     */
    private function retireMissingManagedRules(array $capabilities, CarbonInterface $now): int
    {
        $sourceKeys = collect($capabilities)
            ->map(fn (ApprovalCapabilityData $capability): string => $capability->source_key)
            ->values()
            ->all();

        return ApprovalRule::query()
            ->where('managed_by_config', true)
            ->whereNotNull('source_key')
            ->when(
                $sourceKeys !== [],
                fn ($query) => $query->whereNotIn('source_key', $sourceKeys),
                fn ($query): Builder => $query,
            )
            ->whereNull('retired_at')
            ->get()
            ->reduce(function (int $count, ApprovalRule $approvalRule) use ($now): int {
                $approvalRule->forceFill([
                    'enabled' => false,
                    'retired_at' => $now,
                    'retired_reason' => __('Capability removed from config/approval-sot.php.'),
                ])->save();

                return $count + 1;
            }, 0);
    }
}
