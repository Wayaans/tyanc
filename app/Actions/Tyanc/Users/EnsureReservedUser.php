<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class EnsureReservedUser
{
    /**
     * @param  array{name?: string, username?: string, email?: string, password?: string, locale?: string, timezone?: string}  $attributes
     */
    public function handle(string $reservedKey, array $attributes = []): User
    {
        return DB::transaction(function () use ($reservedKey, $attributes): User {
            $definition = $this->definition($reservedKey);
            $roleName = $this->roleName($reservedKey);
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role instanceof Role) {
                throw new ModelNotFoundException(sprintf('Reserved role [%s] does not exist.', $roleName));
            }

            $user = User::query()->withTrashed()->firstOrNew([
                'reserved_key' => $reservedKey,
            ]);

            $email = $this->stringValue($attributes['email'] ?? $definition['email'] ?? null);
            $username = $this->stringValue($attributes['username'] ?? $definition['username'] ?? null);
            $name = $this->stringValue($attributes['name'] ?? $definition['name'] ?? null) ?? $roleName;
            $password = $this->stringValue($attributes['password'] ?? null);
            $locale = $this->stringValue($attributes['locale'] ?? $definition['locale'] ?? null) ?? (string) config('app.locale', 'en');
            $timezone = $this->stringValue($attributes['timezone'] ?? $definition['timezone'] ?? null) ?? 'UTC';

            if ($email === null || $username === null) {
                throw ValidationException::withMessages([
                    'reserved_user' => __('Reserved user identity is incomplete.'),
                ]);
            }

            $this->assertEmailAvailability($user, $email);
            $this->assertUsernameAvailability($user, $username);

            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'status' => UserStatus::Active,
                'timezone' => $timezone,
                'locale' => $locale,
                'is_reserved' => true,
                'reserved_key' => $reservedKey,
                'email_verified_at' => now(),
            ]);

            if ($password !== null) {
                $user->forceFill([
                    'password' => $password,
                ]);
            }

            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            $user->preference()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'locale' => $locale,
                    'timezone' => $timezone,
                    'appearance' => $reservedKey === 'super_admin' ? 'dark' : 'light',
                    'sidebar_variant' => 'inset',
                    'spacing_density' => 'default',
                ],
            );

            $user->syncRoles([$role]);
            $user->syncPermissions([]);

            return $user->fresh(['preference', 'roles']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $reservedKey): array
    {
        $definition = config(sprintf('tyanc.reserved_users.%s', $reservedKey));

        return is_array($definition) ? $definition : [];
    }

    private function roleName(string $reservedKey): string
    {
        return match ($reservedKey) {
            'super_admin' => (string) config('tyanc.reserved_roles.super_admin'),
            'admin' => (string) config('tyanc.reserved_roles.admin'),
            default => throw ValidationException::withMessages([
                'reserved_key' => __('Unsupported reserved key [:key].', ['key' => $reservedKey]),
            ]),
        };
    }

    private function assertEmailAvailability(User $user, string $email): void
    {
        $conflict = User::query()
            ->withTrashed()
            ->where('email', $email)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'email' => __('The reserved user email is already in use.'),
            ]);
        }
    }

    private function assertUsernameAvailability(User $user, string $username): void
    {
        $conflict = User::query()
            ->withTrashed()
            ->where('username', $username)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'username' => __('The reserved username is already in use.'),
            ]);
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
