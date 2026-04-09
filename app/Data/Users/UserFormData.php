<?php

declare(strict_types=1);

namespace App\Data\Users;

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
     * @param  array<string, string>|null  $social_links
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
        public ?string $email_verified_at,
        public ?string $last_login_at,
        public ?string $last_login_ip,
        public ?string $deleted_at,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $phone_number,
        public ?string $date_of_birth,
        public ?string $gender,
        public ?string $address_line_1,
        public ?string $address_line_2,
        public ?string $city,
        public ?string $state,
        public ?string $country,
        public ?string $postal_code,
        public ?string $company_name,
        public ?string $job_title,
        public ?string $bio,
        public ?array $social_links,
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
            email_verified_at: null,
            last_login_at: null,
            last_login_ip: null,
            deleted_at: null,
            first_name: null,
            last_name: null,
            phone_number: null,
            date_of_birth: null,
            gender: null,
            address_line_1: null,
            address_line_2: null,
            city: null,
            state: null,
            country: null,
            postal_code: null,
            company_name: null,
            job_title: null,
            bio: null,
            social_links: null,
            created_at: now()->toIso8601String(),
            updated_at: now()->toIso8601String(),
        );
    }

    public static function fromModel(User $user): self
    {
        $user->loadMissing('profile', 'roles', 'permissions');

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
            email_verified_at: $user->email_verified_at?->toIso8601String(),
            last_login_at: $user->last_login_at?->toIso8601String(),
            last_login_ip: $user->last_login_ip,
            deleted_at: $user->deleted_at?->toIso8601String(),
            first_name: $user->profile?->first_name,
            last_name: $user->profile?->last_name,
            phone_number: $user->profile?->phone_number,
            date_of_birth: $user->profile?->date_of_birth?->toDateString(),
            gender: $user->profile?->gender,
            address_line_1: $user->profile?->address_line_1,
            address_line_2: $user->profile?->address_line_2,
            city: $user->profile?->city,
            state: $user->profile?->state,
            country: $user->profile?->country,
            postal_code: $user->profile?->postal_code,
            company_name: $user->profile?->company_name,
            job_title: $user->profile?->job_title,
            bio: $user->profile?->bio,
            social_links: $user->profile?->social_links,
            created_at: $user->created_at instanceof CarbonInterface ? $user->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $user->updated_at instanceof CarbonInterface ? $user->updated_at->toIso8601String() : now()->toIso8601String(),
        );
    }
}
