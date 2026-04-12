<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Data\Tyanc\Users\UserFormData;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

final readonly class PersistUserUpdate
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, User $user, array $attributes): User
    {
        $user->loadMissing('roles', 'permissions');

        $roles = $this->names($attributes['roles'] ?? []);
        $permissions = $this->names($attributes['permissions'] ?? []);

        return DB::transaction(function () use ($actor, $user, $attributes, $roles, $permissions): User {
            $before = UserFormData::fromModel($user->fresh(['roles', 'permissions']))->toArray();

            $email = is_string($attributes['email'] ?? null)
                ? $attributes['email']
                : $user->email;
            $emailChanged = $user->email !== $email;
            $emailVerificationEnabled = Features::enabled(Features::emailVerification());

            $user->fill([
                'name' => $this->resolveDisplayName($attributes, $user),
                'username' => is_string($attributes['username'] ?? null) ? $attributes['username'] : $user->username,
                'email' => $email,
                'avatar' => $this->storeAvatar(
                    currentAvatar: $user->avatar,
                    payload: $attributes,
                ),
                'status' => $attributes['status'] ?? $user->status ?? UserStatus::Active,
                'timezone' => is_string($attributes['timezone'] ?? null) ? $attributes['timezone'] : $user->timezone,
                'locale' => is_string($attributes['locale'] ?? null) ? $attributes['locale'] : $user->locale,
                'email_verified_at' => $emailChanged
                    ? ($emailVerificationEnabled ? null : now())
                    : $user->email_verified_at,
            ]);
            $user->save();

            $password = $this->passwordValue($attributes);

            if ($password !== null) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }

            if ($emailChanged && $emailVerificationEnabled) {
                $user->sendEmailVerificationNotification();
            }

            $user->syncRoles($roles);
            $user->syncPermissions($permissions);
            $user->loadMissing('roles', 'permissions');

            activity('users')
                ->performedOn($user)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => UserFormData::fromModel($user)->toArray(),
                ])
                ->log('User updated');

            return $user;
        });
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveDisplayName(array $attributes, User $user): string
    {
        $name = $this->nullableString($attributes['name'] ?? null);

        return $name ?? ($user->name !== '' ? $user->name : $user->username);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeAvatar(?string $currentAvatar, array $payload): ?string
    {
        $uploadedAvatar = $payload['avatar'] ?? null;

        if ($uploadedAvatar instanceof UploadedFile) {
            $storedPath = $uploadedAvatar->store('avatars', 'public');

            if (is_string($currentAvatar) && $currentAvatar !== '') {
                Storage::disk('public')->delete($currentAvatar);
            }

            return is_string($storedPath) ? $storedPath : null;
        }

        if ((bool) ($payload['remove_avatar'] ?? false) && is_string($currentAvatar) && $currentAvatar !== '') {
            Storage::disk('public')->delete($currentAvatar);

            return null;
        }

        return $currentAvatar;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function passwordValue(array $attributes): ?string
    {
        $password = is_string($attributes['password'] ?? null)
            ? mb_trim($attributes['password'])
            : '';

        return $password !== '' ? $password : null;
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
