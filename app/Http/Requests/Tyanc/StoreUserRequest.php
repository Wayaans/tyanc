<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(User::class, 'username'),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                new ValidEmail,
                Rule::unique(User::class, 'email'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::in(UserStatus::values())],
            'locale' => ['required', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['required', 'timezone'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }
}
