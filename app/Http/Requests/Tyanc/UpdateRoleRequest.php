<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? ($this->user()?->can('update', $role) ?? false)
            : false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        assert($role instanceof Role);

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique(Role::class, 'name')->ignore($role->id)],
            'level' => ['required', 'integer', 'min:0'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
