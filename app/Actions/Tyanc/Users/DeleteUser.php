<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class DeleteUser
{
    public function __construct(
        private SubmitGovernedAction $governedActions,
        private DestroyUser $destroyUser,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(User $actor, User $user, array $attributes = []): array
    {
        $this->assertDeletable($actor, $user);

        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $actor,
            permissionName: PermissionKey::tyanc('users', 'delete'),
            subject: $user,
            context: [
                'request_note' => $requestNote,
            ],
            definition: [
                'execute' => fn (): mixed => $this->destroyUser->handle($actor, $user),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Delete user'),
                        'subject_label' => $user->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $user->approvalSubjectSnapshot(),
                ],
            ],
        );
    }

    private function assertDeletable(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('delete', $user);

        if ($user->isDeleteProtected()) {
            throw ValidationException::withMessages([
                'user' => __('Reserved users cannot be deleted.'),
            ]);
        }
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
