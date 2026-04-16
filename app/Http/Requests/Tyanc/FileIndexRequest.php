<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Support\Permissions\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;

final class FileIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('files', 'viewany'));
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
            allowedSorts: ['name', 'file_name', 'app_key', 'folder_path', 'mime_type', 'size', 'created_at'],
            allowedFilters: ['search', 'app_key', 'folder_path', 'mime_group', 'source'],
            defaultSort: ['-created_at'],
            allowedColumns: ['file_name', 'mime_type', 'context', 'size_human', 'uploaded_by_name', 'created_at'],
        );
    }
}
