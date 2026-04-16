<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Files\ManagedFileData;
use App\Models\FileLibrary;
use App\Models\ManagedFile;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class DeleteFile
{
    public function __construct(private PermissionResourceAccess $permissionAccess) {}

    public function handle(User $actor, ManagedFile $file): void
    {
        throw_if(
            ! $this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'delete')),
            AuthorizationException::class,
        );
        if (! $file->is_deletable) {
            throw ValidationException::withMessages([
                'file' => __('This file cannot be deleted from Tyanc.'),
            ]);
        }

        DB::transaction(function () use ($actor, $file): void {
            $before = ManagedFileData::fromModel($file)->toArray();

            if ($file->source === ManagedFile::SourceMediaLibrary) {
                $this->deleteSharedMediaFile($file);
            } elseif ($file->subject_type === User::class) {
                $this->deleteUserAvatar($file);
            } else {
                throw ValidationException::withMessages([
                    'file' => __('This file cannot be deleted from Tyanc.'),
                ]);
            }

            $file->delete();

            activity('files')
                ->performedOn($file)
                ->causedBy($actor)
                ->event('deleted')
                ->withProperties([
                    'old' => $before,
                ])
                ->log('File deleted');
        });
    }

    private function deleteSharedMediaFile(ManagedFile $file): void
    {
        $library = FileLibrary::shared();
        $media = Media::query()->find($file->media_id);

        throw_if(
            ! $media instanceof Media
                || $media->model_type !== FileLibrary::class
                || (string) $media->model_id !== (string) $library->id,
            ModelNotFoundException::class,
        );

        $media->delete();
    }

    private function deleteUserAvatar(ManagedFile $file): void
    {
        if (Storage::disk($file->disk)->exists($file->relative_path)) {
            Storage::disk($file->disk)->delete($file->relative_path);
        }

        if ($file->subject_id === null || $file->subject_id === '') {
            return;
        }

        $user = User::query()->find($file->subject_id);

        if ($user instanceof User && $user->avatar === $file->relative_path) {
            $user->forceFill([
                'avatar' => null,
            ])->save();
        }
    }
}
