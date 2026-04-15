<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Files;

use App\Models\App;
use App\Models\FileLibrary;
use App\Models\ImportRun;
use App\Models\ManagedFile;
use App\Models\SettingsAsset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class SyncManagedFiles
{
    public function handle(): void
    {
        $disk = ManagedFile::PublicDisk;
        $storage = Storage::disk($disk);
        $paths = collect($storage->allFiles())
            ->map(fn (string $path): string => mb_ltrim($path, '/'))
            ->filter(fn (string $path): bool => $path !== '' && ! $this->shouldIgnorePath($path))
            ->values();

        $now = now();
        $appLabels = array_replace(
            collect((array) config('sidebar-menu.apps', []))
                ->mapWithKeys(fn (array $app, string $key): array => [
                    $key => (string) ($app['title'] ?? Str::of($key)->title()->value()),
                ])
                ->all(),
            App::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->pluck('label', 'key')
                ->all(),
        );
        $usersByAvatar = User::query()
            ->whereNotNull('avatar')
            ->get(['id', 'name', 'avatar'])
            ->filter(fn (User $user): bool => is_string($user->avatar) && $user->avatar !== '')
            ->keyBy(fn (User $user): string => mb_ltrim((string) $user->avatar, '/'));
        $mediaByPath = Media::query()
            ->where('disk', $disk)
            ->get()
            ->keyBy(fn (Media $media): string => mb_ltrim($media->getPathRelativeToRoot(), '/'));
        $importRuns = ImportRun::query()
            ->with('creator')
            ->whereIn('id', $mediaByPath->filter(fn (Media $media): bool => $media->model_type === ImportRun::class)->pluck('model_id'))
            ->get()
            ->keyBy(fn (ImportRun $importRun): string => (string) $importRun->id);

        $records = $paths
            ->map(function (string $path) use ($appLabels, $disk, $importRuns, $mediaByPath, $now, $storage, $usersByAvatar): ?array {
                try {
                    $lastModified = Date::createFromTimestamp($storage->lastModified($path));
                    $size = (int) $storage->size($path);
                    $mimeType = (string) $storage->mimeType($path);
                } catch (Throwable) {
                    return null;
                }

                $media = $mediaByPath->get($path);
                $fileName = $media instanceof Media ? (string) $media->file_name : basename($path);
                $directoryPath = mb_ltrim(pathinfo($path, PATHINFO_DIRNAME), '.');
                $directoryPath = $directoryPath === '/' ? '' : mb_trim($directoryPath, '/');

                $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: null;
                $mimeType = $media instanceof Media && $media->mime_type !== ''
                    ? (string) $media->mime_type
                    : $mimeType;
                $mimeGroup = str_contains($mimeType, '/') ? (string) Str::before($mimeType, '/') : 'application';
                $context = $media instanceof Media
                    ? $this->contextForMedia($media, $appLabels, $importRuns)
                    : $this->contextForPublicPath($path, $appLabels, $usersByAvatar);

                return [
                    'disk' => $disk,
                    'source' => $media instanceof Media ? ManagedFile::SourceMediaLibrary : ManagedFile::SourcePublicDisk,
                    'app_key' => $context['app_key'],
                    'resource_key' => $context['resource_key'],
                    'folder_path' => $context['folder_path'],
                    'relative_path' => $path,
                    'directory_path' => $directoryPath,
                    'name' => $context['name'] ?? pathinfo($fileName, PATHINFO_FILENAME),
                    'file_name' => $fileName,
                    'extension' => $extension,
                    'mime_type' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
                    'mime_group' => $mimeGroup,
                    'size_bytes' => $size,
                    'collection_name' => $media?->collection_name,
                    'media_id' => $media?->id,
                    'subject_type' => $context['subject_type'],
                    'subject_id' => $context['subject_id'],
                    'subject_label' => $context['subject_label'],
                    'uploaded_by_id' => $context['uploaded_by_id'],
                    'uploaded_by_name' => $context['uploaded_by_name'],
                    'custom_properties' => is_array($context['custom_properties'])
                        ? json_encode($context['custom_properties'])
                        : null,
                    'is_deletable' => $context['is_deletable'],
                    'uploaded_at' => $context['uploaded_at'] ?? $lastModified,
                    'last_modified_at' => $lastModified,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($disk, $paths, $records): void {
            if ($records !== []) {
                ManagedFile::query()->upsert(
                    $records,
                    ['disk', 'relative_path'],
                    [
                        'source',
                        'app_key',
                        'resource_key',
                        'folder_path',
                        'directory_path',
                        'name',
                        'file_name',
                        'extension',
                        'mime_type',
                        'mime_group',
                        'size_bytes',
                        'collection_name',
                        'media_id',
                        'subject_type',
                        'subject_id',
                        'subject_label',
                        'uploaded_by_id',
                        'uploaded_by_name',
                        'custom_properties',
                        'is_deletable',
                        'uploaded_at',
                        'last_modified_at',
                        'last_seen_at',
                        'updated_at',
                    ],
                );
            }

            if ($paths->isEmpty()) {
                if (! ManagedFile::query()->where('disk', $disk)->exists()) {
                    return;
                }

                return;
            }

            ManagedFile::query()
                ->where('disk', $disk)
                ->whereNotIn('relative_path', $paths->all())
                ->delete();
        });
    }

    private function shouldIgnorePath(string $path): bool
    {
        if (collect(explode('/', $path))->contains(fn (string $segment): bool => Str::startsWith($segment, '.'))) {
            return true;
        }

        return Str::contains($path, ['/conversions/', '/responsive-images/']);
    }

    /**
     * @param  array<string, string>  $appLabels
     * @param  Collection<string, ImportRun>  $importRuns
     * @return array{
     *     app_key: string,
     *     resource_key: string,
     *     folder_path: string,
     *     subject_type: ?string,
     *     subject_id: ?string,
     *     subject_label: ?string,
     *     uploaded_by_id: ?string,
     *     uploaded_by_name: ?string,
     *     custom_properties: array<string, mixed>|null,
     *     is_deletable: bool,
     *     uploaded_at: Carbon|null,
     *     name?: string,
     * }
     */
    private function contextForMedia(Media $media, array $appLabels, Collection $importRuns): array
    {
        $properties = $media->custom_properties;
        $appKey = $this->stringOrNull(data_get($properties, 'app_key'));
        $resourceKey = $this->stringOrNull(data_get($properties, 'resource_key'));
        $folderPath = $this->stringOrNull(data_get($properties, 'folder_path'));
        $uploadedById = $this->stringOrNull(data_get($properties, 'uploaded_by_id'));
        $uploadedByName = $this->stringOrNull(data_get($properties, 'uploaded_by_name'));
        $subjectLabel = $this->stringOrNull(data_get($properties, 'subject_label'));

        if ($media->model_type === FileLibrary::class) {
            return [
                'app_key' => $appKey ?? 'tyanc',
                'resource_key' => $resourceKey ?? 'files',
                'folder_path' => $folderPath ?? 'tyanc/shared',
                'subject_type' => FileLibrary::class,
                'subject_id' => (string) $media->model_id,
                'subject_label' => $subjectLabel ?? 'Tyanc shared library',
                'uploaded_by_id' => $uploadedById,
                'uploaded_by_name' => $uploadedByName,
                'custom_properties' => $properties !== [] ? $properties : null,
                'is_deletable' => true,
                'uploaded_at' => $media->created_at,
                'name' => $media->name !== '' ? $media->name : pathinfo($media->file_name, PATHINFO_FILENAME),
            ];
        }

        if ($media->model_type === SettingsAsset::class) {
            return [
                'app_key' => $appKey ?? 'tyanc',
                'resource_key' => $resourceKey ?? 'settings',
                'folder_path' => $folderPath ?? $this->settingsFolderPath((string) $media->collection_name),
                'subject_type' => SettingsAsset::class,
                'subject_id' => (string) $media->model_id,
                'subject_label' => $subjectLabel ?? 'App settings',
                'uploaded_by_id' => $uploadedById,
                'uploaded_by_name' => $uploadedByName,
                'custom_properties' => $properties !== [] ? $properties : null,
                'is_deletable' => false,
                'uploaded_at' => $media->created_at,
                'name' => $media->name !== '' ? $media->name : pathinfo($media->file_name, PATHINFO_FILENAME),
            ];
        }

        if ($media->model_type === ImportRun::class) {
            /** @var ImportRun|null $importRun */
            $importRun = $importRuns->get((string) $media->model_id);
            $resourceKey ??= $importRun?->type === ImportRun::TypeUsers ? 'users' : 'imports';
            $folderPath ??= $importRun?->type === ImportRun::TypeUsers ? 'tyanc/users/imports' : 'tyanc/imports';

            return [
                'app_key' => $appKey ?? 'tyanc',
                'resource_key' => $resourceKey,
                'folder_path' => $folderPath,
                'subject_type' => ImportRun::class,
                'subject_id' => (string) $media->model_id,
                'subject_label' => $subjectLabel ?? ($importRun instanceof ImportRun ? sprintf('%s import', Str::of($importRun->type)->replace('_', ' ')->title()->value()) : 'Import run'),
                'uploaded_by_id' => $uploadedById ?? $importRun?->created_by_id,
                'uploaded_by_name' => $uploadedByName ?? $importRun?->creator?->name,
                'custom_properties' => $properties !== [] ? $properties : null,
                'is_deletable' => false,
                'uploaded_at' => $media->created_at,
                'name' => $media->name !== '' ? $media->name : pathinfo($media->file_name, PATHINFO_FILENAME),
            ];
        }

        $inferredAppKey = $appKey ?? $this->inferAppKeyFromModelType($media->model_type, $appLabels);
        $fallbackFolder = $inferredAppKey === ManagedFile::UnassignedAppKey
            ? 'unassigned/media-library'
            : sprintf('%s/media-library', $inferredAppKey);

        return [
            'app_key' => $inferredAppKey,
            'resource_key' => $resourceKey ?? 'files',
            'folder_path' => $folderPath ?? $fallbackFolder,
            'subject_type' => $this->stringOrNull($media->model_type),
            'subject_id' => (string) $media->model_id,
            'subject_label' => $subjectLabel,
            'uploaded_by_id' => $uploadedById,
            'uploaded_by_name' => $uploadedByName,
            'custom_properties' => $properties !== [] ? $properties : null,
            'is_deletable' => false,
            'uploaded_at' => $media->created_at,
            'name' => $media->name !== '' ? $media->name : pathinfo($media->file_name, PATHINFO_FILENAME),
        ];
    }

    /**
     * @param  array<string, string>  $appLabels
     * @param  \Illuminate\Database\Eloquent\Collection<string, User>  $usersByAvatar
     * @return array{
     *     app_key: string,
     *     resource_key: string,
     *     folder_path: string,
     *     subject_type: ?string,
     *     subject_id: ?string,
     *     subject_label: ?string,
     *     uploaded_by_id: ?string,
     *     uploaded_by_name: ?string,
     *     custom_properties: array<string, mixed>|null,
     *     is_deletable: bool,
     *     uploaded_at: Carbon|null,
     * }
     */
    private function contextForPublicPath(string $path, array $appLabels, \Illuminate\Database\Eloquent\Collection $usersByAvatar): array
    {
        /** @var User|null $avatarOwner */
        $avatarOwner = $usersByAvatar->get($path);

        if ($avatarOwner instanceof User) {
            return [
                'app_key' => 'tyanc',
                'resource_key' => 'users',
                'folder_path' => 'tyanc/users/avatars',
                'subject_type' => User::class,
                'subject_id' => (string) $avatarOwner->id,
                'subject_label' => $avatarOwner->name,
                'uploaded_by_id' => null,
                'uploaded_by_name' => null,
                'custom_properties' => null,
                'is_deletable' => true,
                'uploaded_at' => null,
            ];
        }

        $segments = collect(explode('/', $path))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();
        $candidateAppKey = (string) ($segments->first() ?? '');
        $appKey = array_key_exists($candidateAppKey, $appLabels)
            ? $candidateAppKey
            : ManagedFile::UnassignedAppKey;
        $physicalDirectory = mb_trim(pathinfo($path, PATHINFO_DIRNAME), './');
        $folderPath = $appKey === ManagedFile::UnassignedAppKey
            ? sprintf('unassigned/%s', $physicalDirectory !== '' ? $physicalDirectory : 'root')
            : ($physicalDirectory !== '' ? $physicalDirectory : $appKey);
        $resourceKey = $appKey === ManagedFile::UnassignedAppKey
            ? 'files'
            : (string) ($segments->get(1) ?? 'files');

        return [
            'app_key' => $appKey,
            'resource_key' => $resourceKey,
            'folder_path' => $folderPath,
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'uploaded_by_id' => null,
            'uploaded_by_name' => null,
            'custom_properties' => null,
            'is_deletable' => false,
            'uploaded_at' => null,
        ];
    }

    /**
     * @param  array<string, string>  $appLabels
     */
    private function inferAppKeyFromModelType(?string $modelType, array $appLabels): string
    {
        if (! is_string($modelType) || $modelType === '' || ! str_starts_with($modelType, 'App\\Models\\')) {
            return ManagedFile::UnassignedAppKey;
        }

        $segments = explode('\\', Str::after($modelType, 'App\\Models\\'));
        $candidate = isset($segments[1]) ? Str::kebab($segments[0]) : '';

        return array_key_exists($candidate, $appLabels)
            ? $candidate
            : ManagedFile::UnassignedAppKey;
    }

    private function settingsFolderPath(string $collectionName): string
    {
        return match ($collectionName) {
            SettingsAsset::APP_LOGO_COLLECTION => 'tyanc/settings/branding/app-logo',
            SettingsAsset::FAVICON_COLLECTION => 'tyanc/settings/branding/favicon',
            SettingsAsset::LOGIN_COVER_IMAGE_COLLECTION => 'tyanc/settings/branding/login-cover',
            default => 'tyanc/settings/assets',
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $resolved = mb_trim((string) $value);

        return $resolved !== '' ? $resolved : null;
    }
}
