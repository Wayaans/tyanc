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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class UploadFile
{
    public function __construct(
        private PermissionResourceAccess $permissionAccess,
        private SyncManagedFiles $syncManagedFiles,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, ManagedFile>
     */
    public function handle(User $actor, array $attributes): array
    {
        throw_if(
            ! $this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'upload')),
            AuthorizationException::class,
        );

        return DB::transaction(function () use ($actor, $attributes): array {
            $library = FileLibrary::shared();

            $uploadedMedia = Collection::make((array) ($attributes['files'] ?? []))
                ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
                ->map(fn (UploadedFile $file): Media => $library
                    ->addMedia($file)
                    ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->withCustomProperties([
                        'app_key' => 'tyanc',
                        'resource_key' => 'files',
                        'folder_path' => 'tyanc/shared',
                        'subject_label' => 'Tyanc shared library',
                        'uploaded_by_id' => (string) $actor->id,
                        'uploaded_by_name' => $actor->name,
                    ])
                    ->toMediaCollection(FileLibrary::FilesCollection))
                ->values();

            $this->syncManagedFiles->handle();

            $managedFiles = ManagedFile::query()
                ->whereIn('media_id', $uploadedMedia->pluck('id'))
                ->get()
                ->keyBy('media_id');

            $uploadedFiles = $uploadedMedia
                ->map(fn (Media $media): ?ManagedFile => $managedFiles->get($media->id))
                ->filter(fn (mixed $file): bool => $file instanceof ManagedFile)
                ->values()
                ->all();

            if ($uploadedFiles !== []) {
                activity('files')
                    ->performedOn($library)
                    ->causedBy($actor)
                    ->event('uploaded')
                    ->withProperties([
                        'files' => array_map(
                            fn (ManagedFile $file): array => ManagedFileData::fromModel($file)->toArray(),
                            $uploadedFiles,
                        ),
                    ])
                    ->log('Files uploaded');
            }

            return $uploadedFiles;
        });
    }
}
