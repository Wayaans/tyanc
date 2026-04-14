<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Approvals\ApprovalCapabilityData;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ToggleApprovalRule
{
    public function __construct(
        private ResolveApprovalCapability $capabilities,
        private ResolveApprovalRuleReadiness $readiness,
    ) {}

    public function handle(User $actor, ApprovalRule $approvalRule, bool $enabled): ApprovalRule
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'manage')),
            AuthorizationException::class,
        );

        $capability = $this->capabilities->handle((string) $approvalRule->permission_name);

        if (! $capability instanceof ApprovalCapabilityData || ! $capability->managed) {
            throw ValidationException::withMessages([
                'approval_rule' => __('Only config-managed approval rules can be toggled.'),
            ]);
        }

        if (! $capability->toggleable) {
            throw ValidationException::withMessages([
                'approval_rule' => __('This approval capability cannot be toggled at runtime.'),
            ]);
        }

        if (! $approvalRule->managed_by_config || $approvalRule->retired_at !== null) {
            throw ValidationException::withMessages([
                'approval_rule' => __('This approval rule is not available for runtime toggling.'),
            ]);
        }

        if ($enabled) {
            $readiness = $this->readiness->handle($approvalRule);

            if (! $readiness['ready']) {
                throw ValidationException::withMessages([
                    'approval_rule' => $readiness['issues'][0] ?? __('Complete the workflow settings before enabling this approval rule.'),
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $approvalRule, $enabled): ApprovalRule {
            $approvalRule->forceFill([
                'enabled' => $enabled,
            ])->save();

            activity('approvals')
                ->performedOn($approvalRule)
                ->causedBy($actor)
                ->event('rule-toggled')
                ->withProperties([
                    'enabled' => $enabled,
                    'permission_name' => $approvalRule->permission_name,
                ])
                ->log('Approval rule toggled');

            return $approvalRule->fresh('steps.role');
        });
    }
}
