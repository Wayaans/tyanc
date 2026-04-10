<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! (bool) config('tyanc.features.imports_enabled', false)) {
            return false;
        }

        $user = $this->user();

        return $user !== null
            && resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('users', 'import'));
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw_unless((bool) config('tyanc.features.imports_enabled', false), NotFoundHttpException::class);

        throw new AuthorizationException();
    }
}
