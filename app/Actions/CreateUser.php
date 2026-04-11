<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use SensitiveParameter;

final readonly class CreateUser
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($attributes, $password): User {
            $username = $this->resolveUsername($attributes);

            $user = new User();
            $user->forceFill([
                'name' => $this->resolveDisplayName($attributes, $username),
                'username' => $username,
                'email' => (string) $attributes['email'],
                'password' => $password,
                'avatar' => $this->storeAvatar($attributes['avatar'] ?? null),
                'status' => $attributes['status'] ?? UserStatus::Active,
                'timezone' => $attributes['timezone'] ?? config('app.timezone', 'UTC'),
                'locale' => $attributes['locale'] ?? config('app.locale', 'en'),
                'is_reserved' => (bool) ($attributes['is_reserved'] ?? false),
                'reserved_key' => $this->nullableString($attributes['reserved_key'] ?? null),
                'email_verified_at' => Features::enabled(Features::emailVerification()) ? null : now(),
            ]);
            $user->save();

            event(new Registered($user));

            return $user->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveUsername(array $attributes): string
    {
        $base = $attributes['username'] ?? str((string) ($attributes['email'] ?? ''))->before('@')->value();

        if ((! is_string($base) || $base === '') && isset($attributes['name']) && is_string($attributes['name'])) {
            $base = $attributes['name'];
        }

        $username = str((string) $base)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-_')
            ->value();

        if ($username === '') {
            $username = 'user';
        }

        $candidate = $username;
        $suffix = 2;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = sprintf('%s-%d', $username, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveDisplayName(array $attributes, string $fallback): string
    {
        $name = $this->nullableString($attributes['name'] ?? null) ?? collect([
            $this->nullableString($attributes['first_name'] ?? null),
            $this->nullableString($attributes['last_name'] ?? null),
        ])->filter()->implode(' ');

        return $name !== '' ? $name : $fallback;
    }

    private function storeAvatar(mixed $avatar): ?string
    {
        if (! $avatar instanceof UploadedFile) {
            return null;
        }

        return $avatar->store('avatars', 'public');
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
