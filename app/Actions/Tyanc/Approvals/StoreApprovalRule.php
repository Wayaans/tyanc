<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class StoreApprovalRule
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes): ApprovalRule
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'create')),
            AuthorizationException::class,
        );

        $validator = Validator::make($attributes, [
            'app_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'resource_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'action_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'enabled' => ['sometimes', 'boolean'],
            'role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'step_label' => ['nullable', 'string', 'max:120'],
        ]);

        $validator->after(function ($validator) use ($attributes): void {
            $permissionName = $this->permissionName($attributes);

            if ($permissionName === null) {
                $validator->errors()->add('action_key', __('Choose a valid governed action.'));

                return;
            }

            if (ApprovalRule::query()->where('permission_name', $permissionName)->exists()) {
                $validator->errors()->add('action_key', __('An approval rule for this action already exists.'));
            }
        });

        /** @var array{app_key: string, resource_key: string, action_key: string, enabled?: bool, role_id: int, step_label?: string|null} $validated */
        $validated = $validator->validate();
        $permissionName = $this->permissionName($validated);

        if ($permissionName === null) {
            throw ValidationException::withMessages([
                'action_key' => __('Choose a valid governed action.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $validated, $permissionName): ApprovalRule {
            $rule = ApprovalRule::query()->create([
                'app_key' => $validated['app_key'],
                'resource_key' => $validated['resource_key'],
                'action_key' => $validated['action_key'],
                'permission_name' => $permissionName,
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'workflow_type' => ApprovalRule::WorkflowSingle,
                'conditions' => null,
            ]);

            $rule->steps()->create([
                'role_id' => $validated['role_id'],
                'step_order' => 1,
                'label' => $this->nullableString($validated['step_label'] ?? null),
            ]);

            activity('approvals')
                ->performedOn($rule)
                ->causedBy($actor)
                ->event('rule-created')
                ->withProperties([
                    'attributes' => $rule->load('steps.role')->toArray(),
                ])
                ->log('Approval rule created');

            return $rule->fresh('steps.role');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function permissionName(array $attributes): ?string
    {
        $appKey = is_string($attributes['app_key'] ?? null) ? $attributes['app_key'] : null;
        $resourceKey = is_string($attributes['resource_key'] ?? null) ? $attributes['resource_key'] : null;
        $actionKey = is_string($attributes['action_key'] ?? null) ? $attributes['action_key'] : null;

        if ($appKey === null || $resourceKey === null || $actionKey === null) {
            return null;
        }

        $permissionName = PermissionKey::make($appKey, $resourceKey, $actionKey);

        return PermissionKey::existsInSource($permissionName) ? $permissionName : null;
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
