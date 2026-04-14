<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\Tyanc\Approvals\DetectApprovalMode;
use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Actions\Tyanc\Approvals\ResolveApprovalRule;
use App\Actions\Tyanc\Approvals\ShouldBypassApproval;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;

final readonly class UpdateUser
{
    public function __construct(
        private DetectApprovalMode $approvalMode,
        private ResolveApprovalRule $approvalRules,
        private ShouldBypassApproval $bypassApproval,
        private ExecuteApprovalControlledAction $governedActions,
        private PrepareUserUpdate $prepareUserUpdate,
        private PersistUserUpdate $persistUserUpdate,
        private StoreUserUpdateDraft $storeUserUpdateDraft,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool, mode: string, requires_draft_submission: bool, draft: UserUpdateDraft|null, saved_draft: bool}
     */
    public function handle(User $actor, User $user, array $attributes): array
    {
        $payload = $this->prepareUserUpdate->handle($actor, $user, $attributes);
        $requestNote = $this->nullableString($payload['request_note'] ?? null);
        $context = [
            ...$payload,
            'request_note' => $requestNote,
            'changed_fields' => $this->changedFields($user, $payload),
            'target_role_levels' => $this->targetRoleLevels($payload['roles'] ?? []),
        ];
        $permissionName = PermissionKey::tyanc('users', 'update');
        $mode = $this->approvalMode->handle($actor, $permissionName, $user, $context);

        if ($mode === ApprovalMode::Draft) {
            $rule = $this->approvalRules->handle($actor, $permissionName, $user, $context);
            $bypassed = $rule instanceof ApprovalRule && $this->bypassApproval->handle($actor, $rule);

            if ($bypassed) {
                $managedUser = $this->persistUserUpdate->handle($actor, $user, $payload);

                activity('approvals')
                    ->performedOn($user)
                    ->causedBy($actor)
                    ->event('bypassed')
                    ->withProperties([
                        'permission_name' => $permissionName,
                        'subject_type' => $user->getMorphClass(),
                        'subject_id' => (string) $user->getKey(),
                    ])
                    ->log('Approval bypassed and action executed');

                return [
                    'executed' => true,
                    'result' => $managedUser,
                    'approval' => null,
                    'bypassed' => true,
                    'mode' => $mode->value,
                    'requires_draft_submission' => false,
                    'draft' => null,
                    'saved_draft' => false,
                ];
            }

            $draft = $this->storeUserUpdateDraft->handle($actor, $user, $payload);

            return [
                'executed' => false,
                'result' => null,
                'approval' => null,
                'bypassed' => false,
                'mode' => $mode->value,
                'requires_draft_submission' => true,
                'draft' => $draft,
                'saved_draft' => true,
            ];
        }

        $submission = $this->governedActions->handle(
            actor: $actor,
            permissionName: $permissionName,
            subject: $user,
            context: $context,
            definition: [
                'execute' => fn (): User => $this->persistUserUpdate->handle($actor, $user, $payload),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update user'),
                        'subject_label' => $user->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $user->approvalSubjectSnapshot(),
                ],
            ],
        );

        return [
            ...$submission,
            'draft' => null,
            'saved_draft' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function changedFields(User $user, array $attributes): array
    {
        $user->loadMissing('roles', 'permissions');

        $changedFields = collect();

        if (($attributes['name'] ?? $user->name) !== $user->name) {
            $changedFields->push('name');
        }

        if (($attributes['username'] ?? $user->username) !== $user->username) {
            $changedFields->push('username');
        }

        if (($attributes['email'] ?? $user->email) !== $user->email) {
            $changedFields->push('email');
        }

        if (($attributes['status'] ?? $user->status->value) !== $user->status->value) {
            $changedFields->push('status');
        }

        if (($attributes['locale'] ?? $user->locale) !== $user->locale) {
            $changedFields->push('locale');
        }

        if (($attributes['timezone'] ?? $user->timezone) !== $user->timezone) {
            $changedFields->push('timezone');
        }

        $roles = $attributes['roles'] ?? $user->roles->pluck('name')->all();

        $nextRoles = collect(is_array($roles) ? $roles : [])
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->sort()
            ->values()
            ->all();

        $currentRoles = $user->roles->pluck('name')->filter()->sort()->values()->all();

        if ($nextRoles !== $currentRoles) {
            $changedFields->push('roles');
        }

        $permissions = $attributes['permissions'] ?? $user->permissions->pluck('name')->all();

        $nextPermissions = collect(is_array($permissions) ? $permissions : [])
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->sort()
            ->values()
            ->all();

        $currentPermissions = $user->permissions->pluck('name')->filter()->sort()->values()->all();

        if ($nextPermissions !== $currentPermissions) {
            $changedFields->push('permissions');
        }

        if (is_string($attributes['password'] ?? null) && mb_trim($attributes['password']) !== '') {
            $changedFields->push('password');
        }

        return $changedFields->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function targetRoleLevels(mixed $roles): array
    {
        if (! is_array($roles) || $roles === []) {
            return [];
        }

        $roleNames = collect($roles)
            ->filter(fn (mixed $role): bool => is_string($role) && mb_trim($role) !== '')
            ->map(fn (string $role): string => mb_trim($role))
            ->unique()
            ->values()
            ->all();

        if ($roleNames === []) {
            return [];
        }

        return Role::query()
            ->whereIn('name', $roleNames)
            ->pluck('level')
            ->filter(fn (mixed $level): bool => is_numeric($level))
            ->map(fn (mixed $level): int => (int) $level)
            ->values()
            ->all();
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
