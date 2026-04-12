<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Data\Tyanc\Users\UserFormData;
use App\Enums\UserStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\Gate;

final readonly class SuspendUser
{
    public function __construct(private SubmitGovernedAction $governedActions) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(User $actor, User $user, array $attributes = []): array
    {
        Gate::forUser($actor)->authorize('suspend', $user);

        return $this->governedActions->handle(
            actor: $actor,
            permissionName: PermissionKey::tyanc('users', 'suspend'),
            subject: $user,
            context: [
                'request_note' => $attributes['request_note'] ?? null,
            ],
            definition: [
                'execute' => fn (): User => $this->apply($actor, $user),
                'proposal' => [
                    'request_note' => $this->nullableString($attributes['request_note'] ?? null),
                    'payload' => [
                        'action_label' => __('Suspend user'),
                        'subject_label' => $user->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $user->approvalSubjectSnapshot(),
                ],
            ],
        );
    }

    private function apply(User $actor, User $user): User
    {
        $before = UserFormData::fromModel($user->fresh(['roles', 'permissions']))->toArray();

        if ($user->status !== UserStatus::Suspended) {
            $user->forceFill([
                'status' => UserStatus::Suspended,
            ])->save();
        }

        $user->loadMissing('roles', 'permissions');

        activity('users')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'old' => $before,
                'attributes' => UserFormData::fromModel($user)->toArray(),
            ])
            ->log('User suspended');

        return $user;
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
