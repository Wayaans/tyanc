<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use SensitiveParameter;

final readonly class CreateUser
{
    public function __construct(private UpsertUserProfile $profiles) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($attributes, $password): User {
            $username = $this->resolveUsername($attributes);

            $user = new User();
            $user->forceFill([
                'username' => $username,
                'email' => (string) $attributes['email'],
                'password' => $password,
                'avatar' => $this->storeAvatar($attributes['avatar'] ?? null),
                'status' => $attributes['status'] ?? UserStatus::Active,
                'timezone' => $attributes['timezone'] ?? config('app.timezone', 'UTC'),
                'locale' => $attributes['locale'] ?? config('app.locale', 'en'),
                'email_verified_at' => Features::enabled(Features::emailVerification()) ? null : now(),
                ...$this->legacyNameAttributes($attributes, $username),
            ]);
            $user->save();

            $profile = $this->profiles->handle($user, $attributes);

            if (Schema::hasColumn('users', 'name')) {
                $user->forceFill([
                    'name' => $profile->fullName() ?? $this->resolveDisplayName($attributes, $username),
                ])->saveQuietly();
            }

            event(new Registered($user));

            return $user->load('profile');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveUsername(array $attributes): string
    {
        $base = $attributes['username'] ?? Str::before((string) ($attributes['email'] ?? ''), '@');

        if ((! is_string($base) || $base === '') && isset($attributes['name']) && is_string($attributes['name'])) {
            $base = $attributes['name'];
        }

        $username = Str::of((string) $base)
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

    private function storeAvatar(mixed $avatar): ?string
    {
        if (! $avatar instanceof UploadedFile) {
            return null;
        }

        return $avatar->store('avatars', 'public');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    private function legacyNameAttributes(array $attributes, string $username): array
    {
        if (! Schema::hasColumn('users', 'name')) {
            return [];
        }

        return [
            'name' => $this->resolveDisplayName($attributes, $username),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveDisplayName(array $attributes, string $fallback): string
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

        return $fallback;
    }
}
