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

final class UpdateUserProfileRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
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
            'status' => [
                Rule::prohibitedIf(! $this->canManageStatus()),
                'nullable',
                Rule::in(UserStatus::values()),
            ],
            'locale' => ['nullable', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['nullable', 'timezone'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'social_links' => ['nullable', 'array'],
            'social_links.linkedin' => ['nullable', 'url:http,https', 'max:2048'],
            'social_links.twitter' => ['nullable', 'url:http,https', 'max:2048'],
            'social_links.github' => ['nullable', 'url:http,https', 'max:2048'],
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
