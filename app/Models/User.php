<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar',
        'status',
        'timezone',
        'locale',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['name'];

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    protected function getNameAttribute(): string
    {
        $profile = $this->relationLoaded('profile') ? $this->getRelation('profile') : $this->profile()->first();

        if ($profile instanceof UserProfile && $profile->fullName() !== null) {
            return $profile->fullName();
        }

        return $this->username;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'username' => 'string',
            'email' => 'string',
            'avatar' => 'string',
            'status' => UserStatus::class,
            'timezone' => 'string',
            'locale' => 'string',
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
