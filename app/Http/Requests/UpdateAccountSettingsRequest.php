<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\User;
use App\Rules\ValidEmail;
use App\Support\Permissions\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAccountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(User::class, 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                new ValidEmail,
                Rule::unique(User::class, 'email')->ignore($user->id),
            ],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'status' => [
                Rule::prohibitedIf(! $this->canManageStatus()),
                'nullable',
                Rule::in(UserStatus::values()),
            ],
            'locale' => ['nullable', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    private function canManageStatus(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return true;
        }

        $permissionName = PermissionKey::tyanc('users', 'manage');

        return Permission::query()->where('name', $permissionName)->where('guard_name', 'web')->exists()
            && $user->hasPermissionTo($permissionName);
    }
}
