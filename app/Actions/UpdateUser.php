<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

final readonly class UpdateUser
{
    public function __construct(private UpsertUserProfile $profiles) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $emailChanged = isset($attributes['email']) && $user->email !== $attributes['email'];
            $emailVerificationEnabled = Features::enabled(Features::emailVerification());

            $userAttributes = [
                'username' => $attributes['username'] ?? $user->username,
                'email' => $attributes['email'] ?? $user->email,
                'avatar' => $this->storeAvatar($attributes['avatar'] ?? null, $user->avatar),
                'status' => $attributes['status'] ?? $user->status ?? UserStatus::Active,
                'timezone' => $attributes['timezone'] ?? $user->timezone,
                'locale' => $attributes['locale'] ?? $user->locale,
            ];

            if ($emailChanged) {
                $userAttributes['email_verified_at'] = $emailVerificationEnabled ? null : now();
            }

            $user->fill($userAttributes);
            $user->save();

            $profile = $this->profiles->handle($user, $attributes);

            if (Schema::hasColumn('users', 'name')) {
                $user->forceFill([
                    'name' => $profile->fullName() ?? $this->resolveDisplayName($attributes, $user->username, $user->name),
                ])->saveQuietly();
            }

            if ($emailChanged && $emailVerificationEnabled) {
                $user->sendEmailVerificationNotification();
            }

            return $user->load('profile');
        });
    }

    private function storeAvatar(mixed $avatar, ?string $currentAvatar): ?string
    {
        if (! $avatar instanceof UploadedFile) {
            return $currentAvatar;
        }

        if (is_string($currentAvatar) && $currentAvatar !== '') {
            Storage::disk('public')->delete($currentAvatar);
        }

        return $avatar->store('avatars', 'public');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveDisplayName(array $attributes, string $fallbackUsername, string $currentName): string
    {
        $name = isset($attributes['name']) && is_string($attributes['name'])
            ? mb_trim($attributes['name'])
            : '';

        if ($name !== '') {
            return $name;
        }

        $segments = array_filter([
            is_string($attributes['first_name'] ?? null) ? mb_trim($attributes['first_name']) : null,
            is_string($attributes['last_name'] ?? null) ? mb_trim($attributes['last_name']) : null,
        ]);

        if ($segments !== []) {
            return implode(' ', $segments);
        }

        return $currentName !== '' ? $currentName : $fallbackUsername;
    }
}
