<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Support\Permissions\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;

final class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('files', 'upload'));
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:10240'],
        ];
    }
}
