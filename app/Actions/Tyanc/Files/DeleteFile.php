<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Files\MediaFileData;
use App\Models\FileLibrary;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class DeleteFile
{
    public function handle(User $actor, Media $media): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('files', 'delete')),
            AuthorizationException::class,
        );

        $library = FileLibrary::shared();

        throw_if($media->model_type !== FileLibrary::class || (string) $media->model_id !== (string) $library->id, ModelNotFoundException::class);

        $before = MediaFileData::fromModel($media)->toArray();

        $media->delete();

        activity('files')
            ->performedOn($library)
            ->causedBy($actor)
            ->event('deleted')
            ->withProperties([
                'old' => $before,
            ])
            ->log('File deleted');
    }
}
