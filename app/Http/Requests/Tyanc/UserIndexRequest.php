<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Data\Tables\DataTableQueryData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function tableQuery(): DataTableQueryData
    {
        return DataTableQueryData::fromRequest(
            request: $this,
            allowedSorts: ['name', 'email', 'status', 'locale', 'last_login_at', 'created_at'],
            allowedFilters: ['search', 'status', 'locale', 'role', 'trashed'],
            defaultSort: ['-created_at'],
            allowedColumns: ['name', 'email', 'status', 'locale', 'roles', 'last_login_at', 'created_at'],
        );
    }
}
