<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncAccessMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionKey::tyanc('access_matrix', 'manage')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', Rule::exists(Role::class, 'name'), 'required_without_all:permission_id,role_id,granted'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
            'permission_id' => ['nullable', 'integer', Rule::exists(Permission::class, 'id'), 'required_with:role_id,granted'],
            'role_id' => ['nullable', 'integer', Rule::exists(Role::class, 'id'), 'required_with:permission_id,granted'],
            'granted' => ['nullable', 'boolean', 'required_with:permission_id,role_id'],
            'preview_role' => ['nullable', 'string'],
        ];
    }
}
