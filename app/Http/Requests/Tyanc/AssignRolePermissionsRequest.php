<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Models\Role;
use App\Support\Permissions\PermissionKey;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AssignRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? ($this->user()?->can('assignPermissions', $role) ?? false)
            : false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $role = $this->route('role');

            if (! $role instanceof Role) {
                return;
            }

            $currentPermissions = $role->loadMissing('permissions')->permissions->pluck('name');
            $invalidPermissions = collect((array) $this->input('permissions', []))
                ->filter(fn (mixed $permission): bool => is_string($permission) && mb_trim($permission) !== '')
                ->reject(fn (string $permission): bool => PermissionKey::existsInSource($permission) || $currentPermissions->contains($permission))
                ->values();

            if ($invalidPermissions->isNotEmpty()) {
                $validator->errors()->add('permissions', __('One or more selected permissions are not available from the permission source of truth.'));
            }
        }];
    }
}
