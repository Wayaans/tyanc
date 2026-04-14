<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\Tyanc\Approvals\DetectApprovalMode;
use App\Actions\Tyanc\Approvals\ResolveApprovalRule;
use App\Actions\Tyanc\Approvals\SubmitDraftApproval;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SubmitUserUpdateDraftForApproval
{
    public function __construct(
        private DetectApprovalMode $approvalMode,
        private ResolveApprovalRule $approvalRules,
        private SubmitDraftApproval $submitDraftApproval,
    ) {}

    public function handle(User $actor, User $user, ?string $requestNote = null): ApprovalRequest
    {
        Gate::forUser($actor)->authorize('update', $user);

        $draft = $this->currentDraft($actor, $user);

        if (! $draft instanceof UserUpdateDraft) {
            throw ValidationException::withMessages([
                'draft' => __('Save a draft before requesting approval.'),
            ]);
        }

        $permissionName = PermissionKey::tyanc('users', 'update');
        $context = $this->approvalContext($draft);
        $mode = $this->approvalMode->handle($actor, $permissionName, $draft, $context);

        if ($mode !== ApprovalMode::Draft) {
            throw ValidationException::withMessages([
                'approval' => __('Approval is not currently required for this draft.'),
            ]);
        }

        $rule = $this->approvalRules->handle($actor, $permissionName, $draft, $context);

        if (! $rule instanceof ApprovalRule) {
            throw ValidationException::withMessages([
                'approval' => __('No approval rule is configured for this draft.'),
            ]);
        }

        return $this->submitDraftApproval->handle(
            actor: $actor,
            rule: $rule,
            permissionName: $permissionName,
            subject: $draft,
            attributes: [
                'request_note' => $requestNote,
                'payload' => [
                    'action_label' => __('Update user'),
                    'subject_label' => $user->approvalSubjectLabel(),
                ],
                'subject_snapshot' => $draft->approvalSubjectSnapshot(),
            ],
        );
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
