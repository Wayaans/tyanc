<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Files\MediaFileData;
use App\Models\FileLibrary;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class UploadFile
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, Media>
     */
    public function handle(User $actor, array $attributes): array
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('files', 'upload')),
            AuthorizationException::class,
        );

        return DB::transaction(function () use ($actor, $attributes): array {
            $library = FileLibrary::shared();

            $uploadedFiles = Collection::make((array) ($attributes['files'] ?? []))
                ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
                ->map(fn (UploadedFile $file): Media => $library
                    ->addMedia($file)
                    ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->withCustomProperties([
                        'uploaded_by_id' => (string) $actor->id,
                        'uploaded_by_name' => $actor->name,
                    ])
                    ->toMediaCollection(FileLibrary::FilesCollection))
                ->values()
                ->all();

            if ($uploadedFiles !== []) {
                activity('files')
                    ->performedOn($library)
                    ->causedBy($actor)
                    ->event('uploaded')
                    ->withProperties([
                        'files' => array_map(
                            fn (Media $media): array => MediaFileData::fromModel($media)->toArray(),
                            $uploadedFiles,
                        ),
                    ])
                    ->log('Files uploaded');
            }

            return $uploadedFiles;
        });
    }
}
