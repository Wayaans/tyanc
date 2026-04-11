<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Users;

use App\Data\Auth\UserData;
use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class UserFormData extends Data
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public string $email,
        public ?string $avatar,
        public string $status,
        public string $timezone,
        public string $locale,
        public array $roles,
        public array $permissions,
        public bool $is_reserved,
        public bool $is_delete_protected,
        public ?string $reserved_key,
        public ?string $email_verified_at,
        public ?string $last_login_at,
        public ?string $last_login_ip,
        public ?string $deleted_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function defaults(): self
    {
        return new self(
            id: '',
            name: '',
            username: '',
            email: '',
            avatar: null,
            status: UserStatus::Active->value,
            timezone: (string) config('app.timezone', 'UTC'),
            locale: (string) config('app.locale', 'en'),
            roles: [],
            permissions: [],
            is_reserved: false,
            is_delete_protected: false,
            reserved_key: null,
            email_verified_at: null,
            last_login_at: null,
            last_login_ip: null,
            deleted_at: null,
            created_at: now()->toIso8601String(),
            updated_at: now()->toIso8601String(),
        );
    }

    public static function fromModel(User $user): self
    {
        $user->loadMissing('roles', 'permissions');

        return new self(
            id: (string) $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            avatar: UserData::fromModel($user)->avatar,
            status: $user->status instanceof UserStatus ? $user->status->value : (string) $user->status,
            timezone: $user->timezone,
            locale: $user->locale,
            roles: $user->roles->pluck('name')->filter()->values()->all(),
            permissions: $user->permissions->pluck('name')->filter()->values()->all(),
            is_reserved: $user->isReserved(),
            is_delete_protected: $user->isDeleteProtected(),
            reserved_key: $user->reserved_key,
            email_verified_at: $user->email_verified_at?->toIso8601String(),
            last_login_at: $user->last_login_at?->toIso8601String(),
            last_login_ip: $user->last_login_ip,
            deleted_at: $user->deleted_at?->toIso8601String(),
            created_at: $user->created_at instanceof CarbonInterface ? $user->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $user->updated_at instanceof CarbonInterface ? $user->updated_at->toIso8601String() : now()->toIso8601String(),
        );
    }
}
