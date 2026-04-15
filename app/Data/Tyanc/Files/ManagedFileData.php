<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Files;

use App\Models\ManagedFile;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

final class ManagedFileData extends Data
{
    /**
     * @param  array<string, mixed>|null  $custom_properties
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $file_name,
        public string $relative_path,
        public string $directory_path,
        public ?string $extension,
        public string $mime_type,
        public string $mime_group,
        public int $size_bytes,
        public string $size_human,
        public bool $is_image,
        public bool $is_previewable,
        public bool $is_public,
        public ?string $preview_url,
        public string $url,
        public string $download_url,
        public string $disk,
        public string $storage_path,
        public string $source,
        public string $source_label,
        public string $app_key,
        public string $app_label,
        public string $resource_key,
        public string $folder_path,
        public string $folder_label,
        public ?string $collection_name,
        public ?int $media_id,
        public ?string $subject_type,
        public ?string $subject_id,
        public ?string $subject_label,
        public ?string $uploaded_by_id,
        public ?string $uploaded_by_name,
        public ?array $custom_properties,
        public bool $is_deletable,
        public string $created_at,
        public string $updated_at,
    ) {}

    /**
     * @param  array<string, string>  $appLabels
     */
    public static function fromModel(ManagedFile $file, array $appLabels = []): self
    {
        $mimeType = $file->mime_type !== '' ? $file->mime_type : 'application/octet-stream';
        $mimeGroup = str_contains($mimeType, '/') ? (string) Str::before($mimeType, '/') : 'application';
        $isImage = $mimeGroup === 'image';
        $isPreviewable = $isImage || $mimeGroup === 'video' || $mimeType === 'application/pdf';
        $storageUrl = self::normalizeStorageUrl($file);
        $appLabel = $appLabels[$file->app_key] ?? self::labelize($file->app_key);

        $uploadedAt = $file->uploaded_at;
        $lastModifiedAt = $file->last_modified_at;
        $recordCreatedAt = $file->created_at;
        $recordUpdatedAt = $file->updated_at;

        return new self(
            id: (int) $file->id,
            name: $file->name !== '' ? $file->name : pathinfo($file->file_name, PATHINFO_FILENAME),
            file_name: $file->file_name,
            relative_path: $file->relative_path,
            directory_path: $file->directory_path,
            extension: $file->extension,
            mime_type: $mimeType,
            mime_group: $mimeGroup,
            size_bytes: (int) $file->size_bytes,
            size_human: Number::fileSize((int) $file->size_bytes),
            is_image: $isImage,
            is_previewable: $isPreviewable,
            is_public: $file->disk === ManagedFile::PublicDisk,
            preview_url: $isImage ? $storageUrl : null,
            url: route('tyanc.files.show', $file, absolute: false),
            download_url: route('tyanc.files.download', $file, absolute: false),
            disk: $file->disk,
            storage_path: $file->relative_path,
            source: $file->source,
            source_label: $file->source === ManagedFile::SourceMediaLibrary ? 'Media library' : 'Public disk',
            app_key: $file->app_key,
            app_label: $appLabel,
            resource_key: $file->resource_key,
            folder_path: $file->folder_path,
            folder_label: self::folderLabel($file->folder_path),
            collection_name: $file->collection_name,
            media_id: $file->media_id,
            subject_type: $file->subject_type,
            subject_id: $file->subject_id,
            subject_label: $file->subject_label,
            uploaded_by_id: $file->uploaded_by_id,
            uploaded_by_name: $file->uploaded_by_name,
            custom_properties: is_array($file->custom_properties) ? $file->custom_properties : null,
            is_deletable: $file->is_deletable,
            created_at: $uploadedAt instanceof CarbonInterface
                ? $uploadedAt->toIso8601String()
                : ($recordCreatedAt instanceof CarbonInterface
                    ? $recordCreatedAt->toIso8601String()
                    : now()->toIso8601String()),
            updated_at: $lastModifiedAt instanceof CarbonInterface
                ? $lastModifiedAt->toIso8601String()
                : ($uploadedAt instanceof CarbonInterface
                    ? $uploadedAt->toIso8601String()
                    : ($recordUpdatedAt instanceof CarbonInterface
                        ? $recordUpdatedAt->toIso8601String()
                        : now()->toIso8601String())),
        );
    }

    private static function normalizeStorageUrl(ManagedFile $file): string
    {
        $url = Storage::disk($file->disk)->url($file->relative_path);

        if (config('filesystems.disks.'.$file->disk.'.driver') !== 'local') {
            return $url;
        }

        $path = parse_url((string) $url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $url;
        }

        $query = parse_url((string) $url, PHP_URL_QUERY);
        $fragment = parse_url((string) $url, PHP_URL_FRAGMENT);

        return $path
            .(is_string($query) && $query !== '' ? '?'.$query : '')
            .(is_string($fragment) && $fragment !== '' ? '#'.$fragment : '');
    }

    private static function folderLabel(string $folderPath): string
    {
        return collect(explode('/', $folderPath))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->skip(1)
            ->map(fn (string $segment): string => self::labelize($segment))
            ->whenEmpty(fn ($segments) => $segments->push('Root'))
            ->implode(' / ');
    }

    private static function labelize(string $value): string
    {
        return Str::of($value)
            ->replace(['-', '_'], ' ')
            ->trim()
            ->title()
            ->value();
    }
}
