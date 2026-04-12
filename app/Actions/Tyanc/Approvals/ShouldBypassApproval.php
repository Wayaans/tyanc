<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

final readonly class ShouldBypassApproval
{
    public function handle(User $actor, ?ApprovalRule $rule): bool
    {
        if (! $rule instanceof ApprovalRule) {
            return true;
        }

        if ($actor->hasRole((string) config('tyanc.reserved_roles.super_admin'))) {
            return true;
        }

        $rule->loadMissing(['steps.role']);

        $step = $rule->steps->sortBy('step_order')->first();

        if (! $step instanceof ApprovalRuleStep || ! is_numeric($step->role_id)) {
            return false;
        }

        $access = resolve(PermissionResourceAccess::class);

        return $actor->hasRole((int) $step->role_id)
            && $access->handle($actor, $rule->permission_name)
            && $access->handle($actor, PermissionKey::cumpu('approvals', 'viewany'))
            && (
                $access->handle($actor, PermissionKey::cumpu('approvals', 'approve'))
                || $access->handle($actor, PermissionKey::cumpu('approvals', 'reject'))
                || $access->handle($actor, PermissionKey::cumpu('approvals', 'manage'))
            );
    }
}
