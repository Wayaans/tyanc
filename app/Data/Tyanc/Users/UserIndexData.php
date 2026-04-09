<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class UserIndexData extends Data
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
        public string $locale,
        public string $timezone,
        public array $roles,
        public array $permissions,
        public ?string $last_login_at,
        public ?string $last_login_ip,
        public ?string $deleted_at,
        public string $created_at,
        public string $updated_at,
        public UserFormData $form,
    ) {}

    public static function fromModel(User $user): self
    {
        $user->loadMissing('profile', 'roles', 'permissions');
        $form = UserFormData::fromModel($user);

        return new self(
            id: (string) $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            avatar: $form->avatar,
            status: $user->status instanceof UserStatus ? $user->status->value : (string) $user->status,
            locale: $user->locale,
            timezone: $user->timezone,
            roles: $user->roles->pluck('name')->filter()->values()->all(),
            permissions: $user->permissions->pluck('name')->filter()->values()->all(),
            last_login_at: $user->last_login_at?->toIso8601String(),
            last_login_ip: $user->last_login_ip,
            deleted_at: $user->deleted_at?->toIso8601String(),
            created_at: $user->created_at instanceof CarbonInterface ? $user->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $user->updated_at instanceof CarbonInterface ? $user->updated_at->toIso8601String() : now()->toIso8601String(),
            form: $form,
        );
    }
}
