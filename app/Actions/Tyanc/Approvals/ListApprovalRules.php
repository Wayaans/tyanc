<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ListApprovalRules
{
    /**
     * @return list<array{
     *     id: string,
     *     app_key: string,
     *     app_label: string,
     *     resource_key: string,
     *     resource_label: string,
     *     action_key: string,
     *     action_label: string,
     *     permission_name: string,
     *     enabled: bool,
     *     workflow_type: string,
     *     step_role_id: int|null,
     *     step_role_name: string|null,
     *     step_label: string|null
     * }>
     */
    public function handle(User $actor): array
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'viewany')),
            AuthorizationException::class,
        );

        return ApprovalRule::query()
            ->with(['steps.role'])
            ->orderBy('app_key')
            ->orderBy('resource_key')
            ->orderBy('action_key')
            ->get()
            ->map(function (ApprovalRule $rule): array {
                /** @var ApprovalRuleStep|null $step */
                $step = $rule->steps->sortBy('step_order')->first();

                return [
                    'id' => (string) $rule->id,
                    'app_key' => $rule->app_key,
                    'app_label' => PermissionKey::appLabel((string) $rule->app_key),
                    'resource_key' => $rule->resource_key,
                    'resource_label' => PermissionKey::resourceLabel((string) $rule->app_key, (string) $rule->resource_key),
                    'action_key' => $rule->action_key,
                    'action_label' => PermissionKey::actionLabel((string) $rule->action_key),
                    'permission_name' => (string) $rule->permission_name,
                    'enabled' => $rule->enabled,
                    'workflow_type' => (string) $rule->workflow_type,
                    'step_role_id' => $step?->role_id,
                    'step_role_name' => $step?->role?->name,
                    'step_label' => $step?->label,
                ];
            })
            ->all();
    }
}
