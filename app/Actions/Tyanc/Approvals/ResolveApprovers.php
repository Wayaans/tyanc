<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Collection;

final readonly class ResolveApprovers
{
    /**
     * @return Collection<int, User>
     */
    public function handle(User $actor, ApprovalRule $rule, ?ApprovalRuleStep $step = null): Collection
    {
        $rule->loadMissing(['steps.role']);

        $resolvedStep = $step ?? $rule->steps->sortBy('step_order')->first();

        if (! $resolvedStep instanceof ApprovalRuleStep || ! is_numeric($resolvedStep->role_id)) {
            return collect();
        }

        $actor->loadMissing('roles');
        $actorLevel = $actor->roles->max('level');
        $superAdminRole = (string) config('tyanc.reserved_roles.super_admin');
        $access = resolve(PermissionResourceAccess::class);

        return User::query()
            ->with(['roles.permissions', 'permissions'])
            ->whereHas('roles', fn ($query) => $query->whereKey($resolvedStep->role_id))
            ->get()
            ->filter(function (User $candidate) use ($actor, $actorLevel, $superAdminRole, $access, $rule): bool {
                if ($candidate->is($actor)) {
                    return false;
                }

                if (! $access->handle($candidate, $rule->permission_name)) {
                    return false;
                }

                if (! $access->handle($candidate, PermissionKey::cumpu('approvals', 'viewany'))) {
                    return false;
                }

                if (
                    ! $access->handle($candidate, PermissionKey::cumpu('approvals', 'approve'))
                    && ! $access->handle($candidate, PermissionKey::cumpu('approvals', 'reject'))
                    && ! $access->handle($candidate, PermissionKey::cumpu('approvals', 'manage'))
                ) {
                    return false;
                }

                if ($candidate->hasRole($superAdminRole)) {
                    return true;
                }

                $candidate->loadMissing('roles');
                $candidateLevel = $candidate->roles->max('level');

                if (! is_numeric($actorLevel) || ! is_numeric($candidateLevel)) {
                    return true;
                }

                return (int) $candidateLevel > (int) $actorLevel;
            })
            ->values();
    }
}
