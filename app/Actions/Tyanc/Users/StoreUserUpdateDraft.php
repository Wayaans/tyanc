<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\Tyanc\Approvals\InvalidateStaleDraftApprovals;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\DB;

final readonly class StoreUserUpdateDraft
{
    public function __construct(
        private PrepareUserUpdate $prepareUserUpdate,
        private InvalidateStaleDraftApprovals $invalidateStaleDraftApprovals,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, User $user, array $attributes): UserUpdateDraft
    {
        $payload = $this->prepareUserUpdate->handle($actor, $user, $attributes);
        $currentDraft = $this->currentDraft($actor, $user);
        $draftPayload = $this->draftPayload($currentDraft, $payload, $user);
        $changedFields = $this->changedFields($user, $draftPayload);
        $draftChanged = ! $currentDraft instanceof UserUpdateDraft
            || $this->draftHash($currentDraft->attributesForPersistence(), $currentDraft->changed_fields ?? []) !== $this->draftHash($draftPayload, $changedFields);

        return DB::transaction(function () use ($actor, $user, $currentDraft, $draftPayload, $changedFields, $draftChanged): UserUpdateDraft {
            /** @var UserUpdateDraft|null $lockedDraft */
            $lockedDraft = $currentDraft instanceof UserUpdateDraft
                ? UserUpdateDraft::query()->lockForUpdate()->find($currentDraft->id)
                : null;

            $draft = $lockedDraft ?? new UserUpdateDraft;

            $draft->forceFill([
                'user_id' => $user->id,
                'created_by_id' => $actor->id,
                'revision' => $draftChanged
                    ? (($lockedDraft instanceof UserUpdateDraft ? $lockedDraft->revision : 0) + 1)
                    : ($lockedDraft instanceof UserUpdateDraft ? $lockedDraft->revision : 1),
                'payload' => $draftPayload,
                'changed_fields' => $changedFields,
            ])->save();

            if ($draftChanged && $lockedDraft instanceof UserUpdateDraft) {
                $this->invalidateStaleDraftApprovals->handle(
                    permissionName: PermissionKey::tyanc('users', 'update'),
                    subject: $draft,
                    actor: $actor,
                );
            }

            return $draft->fresh([
                'user',
                'creator',
                'approvalRequests.requester',
                'approvalRequests.consumedBy',
                'approvalRequests.assignments.step',
            ]);
        });
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function draftPayload(?UserUpdateDraft $currentDraft, array $payload, User $user): array
    {
        $draftPayload = [
            'name' => is_string($payload['name'] ?? null) ? $payload['name'] : $user->name,
            'username' => is_string($payload['username'] ?? null) ? $payload['username'] : $user->username,
            'email' => is_string($payload['email'] ?? null) ? $payload['email'] : $user->email,
            'status' => $payload['status'] ?? $user->status->value,
            'locale' => is_string($payload['locale'] ?? null) ? $payload['locale'] : $user->locale,
            'timezone' => is_string($payload['timezone'] ?? null) ? $payload['timezone'] : $user->timezone,
            'roles' => $this->names($payload['roles'] ?? []),
            'permissions' => $this->names($payload['permissions'] ?? []),
        ];

        $password = is_string($payload['password'] ?? null)
            ? mb_trim($payload['password'])
            : '';

        if ($password !== '') {
            $draftPayload['password'] = $password;
        } elseif ($currentDraft instanceof UserUpdateDraft && $currentDraft->hasPasswordChange()) {
            $existingPassword = $currentDraft->attributesForPersistence()['password'] ?? null;

            if (is_string($existingPassword) && mb_trim($existingPassword) !== '') {
                $draftPayload['password'] = $existingPassword;
            }
        }

        return $draftPayload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function changedFields(User $user, array $payload): array
    {
        $user->loadMissing('roles', 'permissions');

        $changedFields = collect();

        if (($payload['name'] ?? $user->name) !== $user->name) {
            $changedFields->push('name');
        }

        if (($payload['username'] ?? $user->username) !== $user->username) {
            $changedFields->push('username');
        }

        if (($payload['email'] ?? $user->email) !== $user->email) {
            $changedFields->push('email');
        }

        if (($payload['status'] ?? $user->status->value) !== $user->status->value) {
            $changedFields->push('status');
        }

        if (($payload['locale'] ?? $user->locale) !== $user->locale) {
            $changedFields->push('locale');
        }

        if (($payload['timezone'] ?? $user->timezone) !== $user->timezone) {
            $changedFields->push('timezone');
        }

        $nextRoles = collect($this->names($payload['roles'] ?? []))
            ->sort()
            ->values()
            ->all();
        $currentRoles = $user->roles->pluck('name')->filter()->sort()->values()->all();

        if ($nextRoles !== $currentRoles) {
            $changedFields->push('roles');
        }

        $nextPermissions = collect($this->names($payload['permissions'] ?? []))
            ->sort()
            ->values()
            ->all();
        $currentPermissions = $user->permissions->pluck('name')->filter()->sort()->values()->all();

        if ($nextPermissions !== $currentPermissions) {
            $changedFields->push('permissions');
        }

        if (is_string($payload['password'] ?? null) && mb_trim($payload['password']) !== '') {
            $changedFields->push('password');
        }

        return $changedFields->unique()->values()->all();
    }

    /**
     * @param  array<int, string>  $changedFields
     * @param  array<string, mixed>  $payload
     */
    private function draftHash(array $payload, array $changedFields): string
    {
        return hash('sha256', json_encode([
            'payload' => $payload,
            'changed_fields' => $changedFields,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<int, string>
     */
    private function names(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn (mixed $value): bool => is_string($value) && mb_trim($value) !== '')
            ->map(fn (string $value): string => mb_trim($value))
            ->unique()
            ->values()
            ->all();
    }
}
