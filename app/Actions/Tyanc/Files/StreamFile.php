<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ManagedFile;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StreamFile
{
    public function __construct(private PermissionResourceAccess $permissionAccess) {}

    public function handle(User $actor, ManagedFile $file): StreamedResponse
    {
        throw_if(! $this->canView($actor), AuthorizationException::class);
        throw_if(Storage::disk($file->disk)->missing($file->relative_path), ModelNotFoundException::class);

        return Storage::disk($file->disk)->response(
            $file->relative_path,
            $file->file_name,
            ['Content-Type' => $file->mime_type],
            'inline',
        );
    }

    private function canView(User $actor): bool
    {
        if ($this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'viewany'))) {
            return true;
        }

        return $this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'view'));
    }
}
