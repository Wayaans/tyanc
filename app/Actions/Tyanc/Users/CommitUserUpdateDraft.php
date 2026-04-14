<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\Tyanc\Approvals\CommitApprovedDraft;
use App\Actions\Tyanc\Approvals\DetectApprovalMode;
use App\Actions\Tyanc\Approvals\ResolveApprovalRule;
use App\Actions\Tyanc\Approvals\ShouldBypassApproval;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CommitUserUpdateDraft
{
    public function __construct(
        private DetectApprovalMode $approvalMode,
        private ResolveApprovalRule $approvalRules,
        private ShouldBypassApproval $bypassApproval,
        private CommitApprovedDraft $commitApprovedDraft,
        private PersistUserUpdate $persistUserUpdate,
    ) {}

    /**
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool, mode: string}
     */
    public function handle(User $actor, User $user): array
    {
        Gate::forUser($actor)->authorize('update', $user);

        $draft = $this->currentDraft($actor, $user);

        if (! $draft instanceof UserUpdateDraft) {
            throw ValidationException::withMessages([
                'draft' => __('Save a draft before committing changes.'),
            ]);
        }

        $permissionName = PermissionKey::tyanc('users', 'update');
        $context = $this->approvalContext($draft);
        $mode = $this->approvalMode->handle($actor, $permissionName, $draft, $context);
        $rule = $this->approvalRules->handle($actor, $permissionName, $draft, $context);
        $bypassed = $rule instanceof ApprovalRule && $this->bypassApproval->handle($actor, $rule);

        if ($mode === ApprovalMode::None || $bypassed) {
            $managedUser = DB::transaction(function () use ($actor, $user, $draft): User {
                $managedUser = $this->persistUserUpdate->handle($actor, $user, $draft->attributesForPersistence());

                $draft->forceFill([
                    'committed_by_id' => $actor->id,
                    'committed_at' => now(),
                ])->save();

                return $managedUser;
            });

            if ($bypassed) {
                activity('approvals')
                    ->performedOn($draft)
                    ->causedBy($actor)
                    ->event('bypassed')
                    ->withProperties([
                        'permission_name' => $permissionName,
                        'subject_type' => $draft->getMorphClass(),
                        'subject_id' => (string) $draft->getKey(),
                    ])
                    ->log('Approval bypassed and action executed');
            }

            return [
                'executed' => true,
                'result' => $managedUser,
                'approval' => null,
                'bypassed' => $bypassed,
                'mode' => $mode->value,
            ];
        }

        if ($mode !== ApprovalMode::Draft) {
            throw ValidationException::withMessages([
                'approval' => __('This draft is not configured for approval-backed commits.'),
            ]);
        }

        $commit = $this->commitApprovedDraft->handle(
            actor: $actor,
            permissionName: $permissionName,
            subject: $draft,
            execute: function () use ($actor, $user, $draft): User {
                $managedUser = $this->persistUserUpdate->handle($actor, $user, $draft->attributesForPersistence());

                $draft->forceFill([
                    'committed_by_id' => $actor->id,
                    'committed_at' => now(),
                ])->save();

                return $managedUser;
            },
        );

        if (! $commit['consumed']) {
            throw ValidationException::withMessages([
                'approval' => $commit['stale']
                    ? __('This approved draft is stale. Save the latest draft and submit it for approval again.')
                    : __('This draft is not approved for commit yet.'),
            ]);
        }

        return [
            'executed' => true,
            'result' => $commit['result'],
            'approval' => $commit['approval'],
            'bypassed' => false,
            'mode' => $mode->value,
        ];
    }

    private function currentDraft(User $actor, User $user): ?UserUpdateDraft
    {
        return UserUpdateDraft::query()
            ->where('user_id', $user->id)
            ->where('created_by_id', $actor->id)
            ->whereNull('committed_at')
            ->latest('updated_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalContext(UserUpdateDraft $draft): array
    {
        return [
            'changed_fields' => $draft->changed_fields ?? [],
            'target_role_levels' => $this->targetRoleLevels($draft),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function targetRoleLevels(UserUpdateDraft $draft): array
    {
        $roles = $draft->attributesForPersistence()['roles'] ?? [];

        if (! is_array($roles) || $roles === []) {
            return [];
        }

        return Role::query()
            ->whereIn('name', collect($roles)
                ->filter(fn (mixed $role): bool => is_string($role) && mb_trim($role) !== '')
                ->map(fn (string $role): string => mb_trim($role))
                ->unique()
                ->values()
                ->all())
            ->pluck('level')
            ->filter(fn (mixed $level): bool => is_numeric($level))
            ->map(fn (mixed $level): int => (int) $level)
            ->values()
            ->all();
    }
}
