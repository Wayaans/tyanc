<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public string $email,
        public ?string $avatar,
        public string $status,
        public string $timezone,
        public string $locale,
        public bool $is_reserved,
        public ?string $reserved_key,
        public ?string $email_verified_at,
        public ?string $last_login_at,
        public ?string $last_login_ip,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: (string) $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            avatar: self::resolveAvatarUrl($user->avatar),
            status: $user->status instanceof UserStatus ? $user->status->value : (string) $user->status,
            timezone: $user->timezone,
            locale: $user->locale,
            is_reserved: $user->isReserved(),
            reserved_key: $user->reserved_key,
            email_verified_at: $user->email_verified_at?->toIso8601String(),
            last_login_at: $user->last_login_at?->toIso8601String(),
            last_login_ip: $user->last_login_ip,
            created_at: $user->created_at instanceof CarbonInterface ? $user->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $user->updated_at instanceof CarbonInterface ? $user->updated_at->toIso8601String() : now()->toIso8601String(),
        );
    }

    private static function resolveAvatarUrl(?string $avatar): ?string
    {
        if ($avatar === null || $avatar === '') {
            return null;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://') || str_starts_with($avatar, '/')) {
            return $avatar;
        }

        return '/storage/'.mb_ltrim($avatar, '/');
    }
}
