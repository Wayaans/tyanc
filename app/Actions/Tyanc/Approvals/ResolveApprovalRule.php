<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class ResolveApprovalRule
{
    public function __construct(
        private ResolveApprovalCapability $capabilities,
        private ResolveApprovalRuleReadiness $readiness,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(User $actor, string $permissionName, ?Model $subject = null, array $context = []): ?ApprovalRule
    {
        $capability = $this->capabilities->handle($permissionName);

        if (! $capability instanceof ApprovalCapabilityData) {
            return null;
        }

        /** @var Collection<int, ApprovalRule> $rules */
        $rules = ApprovalRule::query()
            ->with(['steps.role'])
            ->where('permission_name', $permissionName)
            ->where('enabled', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('retired_at')
                    ->orWhere('managed_by_config', false);
            })
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $managedRules = $rules
            ->filter(fn (ApprovalRule $rule): bool => $rule->managed_by_config && $rule->retired_at === null)
            ->values();

        $candidates = $managedRules->isNotEmpty()
            ? $managedRules
            : $rules->values();

        return $candidates->first(function (ApprovalRule $rule) use ($actor, $subject, $context): bool {
            $readiness = $this->readiness->handle($rule);

            return $readiness['ready'] && $this->matchesConditions($rule, $actor, $subject, $context);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchesConditions(ApprovalRule $rule, User $actor, ?Model $subject, array $context): bool
    {
        $conditions = is_array($rule->conditions) ? $rule->conditions : [];

        if ($conditions === []) {
            return true;
        }

        $actor->loadMissing('roles');
        $actorLevel = $actor->roles->max('level');

        $requesterMinLevel = data_get($conditions, 'requester_min_level');
        if (is_numeric($requesterMinLevel) && (! is_numeric($actorLevel) || (int) $actorLevel < (int) $requesterMinLevel)) {
            return false;
        }

        $requesterMaxLevel = data_get($conditions, 'requester_max_level');
        if (is_numeric($requesterMaxLevel) && is_numeric($actorLevel) && (int) $actorLevel > (int) $requesterMaxLevel) {
            return false;
        }

        $subjectTypes = data_get($conditions, 'subject_types');
        if (is_array($subjectTypes) && $subjectTypes !== [] && (! $subject instanceof Model || ! in_array($subject::class, $subjectTypes, true))) {
            return false;
        }

        $changedFields = data_get($conditions, 'changed_fields');
        if (is_array($changedFields) && $changedFields !== []) {
            $contextChangedFields = collect(is_array(data_get($context, 'changed_fields')) ? data_get($context, 'changed_fields') : [])
                ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
                ->values()
                ->all();

            if ($contextChangedFields === [] || array_intersect($changedFields, $contextChangedFields) === []) {
                return false;
            }
        }

        $targetRoleMaxLevel = data_get($conditions, 'target_role_max_level');
        if (is_numeric($targetRoleMaxLevel)) {
            $targetRoleLevels = collect(is_array(data_get($context, 'target_role_levels')) ? data_get($context, 'target_role_levels') : [])
                ->filter(fn (mixed $level): bool => is_numeric($level))
                ->map(fn (mixed $level): int => (int) $level)
                ->values();

            if ($targetRoleLevels->isNotEmpty() && $targetRoleLevels->max() > (int) $targetRoleMaxLevel) {
                return false;
            }
        }

        return true;
    }
}
