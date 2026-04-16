<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Files\ManagedFileData;
use App\Models\ManagedFile;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadFile
{
    public function __construct(private PermissionResourceAccess $permissionAccess) {}

    public function handle(User $actor, ManagedFile $file): StreamedResponse
    {
        throw_if(
            ! $this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'download')),
            AuthorizationException::class,
        );
        throw_if(Storage::disk($file->disk)->missing($file->relative_path), ModelNotFoundException::class);

        activity('files')
            ->performedOn($file)
            ->causedBy($actor)
            ->event('downloaded')
            ->withProperties([
                'attributes' => ManagedFileData::fromModel($file)->toArray(),
            ])
            ->log('File downloaded');

        return Storage::disk($file->disk)->download(
            $file->relative_path,
            $file->file_name,
            ['Content-Type' => $file->mime_type],
        );
    }
}
