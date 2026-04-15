<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvals\ApprovalSubject;
use App\Enums\UserStatus;
use App\Models\Concerns\InteractsWithApprovals;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property string|null $avatar
 * @property UserStatus $status
 * @property string $timezone
 * @property string $locale
 * @property bool $is_reserved
 * @property string|null $reserved_key
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read UserPreference|null $preference
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 */
#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'avatar',
    'status',
    'timezone',
    'locale',
    'is_reserved',
    'reserved_key',
    'email_verified_at',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'two_factor_confirmed_at',
    'last_login_at',
    'last_login_ip',
    'remember_token',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
final class User extends Authenticatable implements ApprovalSubject, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use InteractsWithApprovals;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * @return HasOne<UserPreference, $this>
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function approvalAppKey(): string
    {
        return 'tyanc';
    }

    public function approvalResourceKey(): string
    {
        return 'users';
    }

    public function approvalSubjectLabel(): string
    {
        return $this->name !== '' ? $this->name : $this->email;
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalSubjectSnapshot(): array
    {
        $this->loadMissing('roles', 'permissions');

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'avatar' => $this->avatar,
            'status' => $this->status->value,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'roles' => $this->roles->pluck('name')->filter()->sort()->values()->all(),
            'permissions' => $this->permissions->pluck('name')->filter()->sort()->values()->all(),
        ];
    }

    public function preferredLocale(): string
    {
        $this->loadMissing('preference');

        $preference = $this->preference;

        return $preference instanceof UserPreference
            ? $preference->locale
            : $this->locale;
    }

    public function isReserved(): bool
    {
        return $this->is_reserved || (is_string($this->reserved_key) && $this->reserved_key !== '');
    }

    public function isDeleteProtected(): bool
    {
        return $this->isReserved();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'username' => 'string',
            'email' => 'string',
            'avatar' => 'string',
            'status' => UserStatus::class,
            'timezone' => 'string',
            'locale' => 'string',
            'is_reserved' => 'boolean',
            'reserved_key' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'remember_token' => 'string',
            'two_factor_secret' => 'string',
            'two_factor_recovery_codes' => 'string',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_login_ip' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
