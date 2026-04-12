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

final readonly class UpdateApprovalRule
{
    public function __construct(private SyncApprovalRuleSteps $syncSteps) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, ApprovalRule $approvalRule, array $attributes): ApprovalRule
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approval_rules', 'update')),
            AuthorizationException::class,
        );

        $validator = Validator::make($attributes, [
            'app_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'resource_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'action_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'enabled' => ['sometimes', 'boolean'],
            'workflow_type' => ['required', 'string', Rule::in([ApprovalRule::WorkflowSingle, ApprovalRule::WorkflowMulti])],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.role_id' => ['required', 'integer', Rule::exists(Role::class, 'id')],
            'steps.*.label' => ['nullable', 'string', 'max:120'],
            'reminder_after_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'escalation_after_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
        ]);

        $validator->after(function ($validator) use ($attributes, $approvalRule): void {
            $permissionName = $this->permissionName($attributes);

            if ($permissionName === null) {
                $validator->errors()->add('action_key', __('Choose a valid governed action.'));

                return;
            }

            if (ApprovalRule::query()->where('permission_name', $permissionName)->whereKeyNot($approvalRule->id)->exists()) {
                $validator->errors()->add('action_key', __('An approval rule for this action already exists.'));
            }
        });

        /** @var array{app_key: string, resource_key: string, action_key: string, enabled?: bool, workflow_type: string, steps: list<array{role_id: int, label?: string|null}>, reminder_after_minutes?: int|null, escalation_after_minutes?: int|null} $validated */
        $validated = $validator->validate();
        $permissionName = $this->permissionName($validated);

        if ($permissionName === null) {
            throw ValidationException::withMessages([
                'action_key' => __('Choose a valid governed action.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $approvalRule, $validated, $permissionName): ApprovalRule {
            $approvalRule->forceFill([
                'app_key' => $validated['app_key'],
                'resource_key' => $validated['resource_key'],
                'action_key' => $validated['action_key'],
                'permission_name' => $permissionName,
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'workflow_type' => $validated['workflow_type'],
                'reminder_after_minutes' => $validated['reminder_after_minutes'] ?? null,
                'escalation_after_minutes' => $validated['escalation_after_minutes'] ?? null,
            ])->save();

            $approvalRule = $this->syncSteps->handle($approvalRule, $validated['steps']);

            activity('approvals')
                ->performedOn($approvalRule)
                ->causedBy($actor)
                ->event('rule-updated')
                ->withProperties([
                    'attributes' => $approvalRule->load('steps.role')->toArray(),
                ])
                ->log('Approval rule updated');

            return $approvalRule;
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
}
