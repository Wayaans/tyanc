<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

final readonly class UpdateUser
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $emailChanged = isset($attributes['email']) && $user->email !== $attributes['email'];
            $emailVerificationEnabled = Features::enabled(Features::emailVerification());

            $userAttributes = [
                'name' => $this->resolveDisplayName($attributes, $user),
                'username' => $attributes['username'] ?? $user->username,
                'email' => $attributes['email'] ?? $user->email,
                'avatar' => $this->storeAvatar(
                    avatar: $attributes['avatar'] ?? null,
                    currentAvatar: $user->avatar,
                    removeAvatar: (bool) ($attributes['remove_avatar'] ?? false),
                ),
                'status' => $attributes['status'] ?? $user->status ?? UserStatus::Active,
                'timezone' => $attributes['timezone'] ?? $user->timezone,
                'locale' => $attributes['locale'] ?? $user->locale,
            ];

            if ($emailChanged) {
                $userAttributes['email_verified_at'] = $emailVerificationEnabled ? null : now();
            }

            $user->fill($userAttributes);
            $user->save();

            if ($emailChanged && $emailVerificationEnabled) {
                $user->sendEmailVerificationNotification();
            }

            return $user->fresh();
        });
    }

    private function storeAvatar(mixed $avatar, ?string $currentAvatar, bool $removeAvatar = false): ?string
    {
        if ($removeAvatar && is_string($currentAvatar) && $currentAvatar !== '') {
            Storage::disk('public')->delete($currentAvatar);
            $currentAvatar = null;
        }

        if (! $avatar instanceof UploadedFile) {
            return $currentAvatar;
        }

        if (is_string($currentAvatar) && $currentAvatar !== '') {
            Storage::disk('public')->delete($currentAvatar);
        }

        $storedPath = $avatar->store('avatars', 'public');

        return is_string($storedPath) ? $storedPath : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveDisplayName(array $attributes, User $user): string
    {
        $name = $this->nullableString($attributes['name'] ?? null) ?? collect([
            $this->nullableString($attributes['first_name'] ?? null),
            $this->nullableString($attributes['last_name'] ?? null),
        ])->filter()->implode(' ');

        if ($name !== '') {
            return $name;
        }

        return $user->name !== '' ? $user->name : $user->username;
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
