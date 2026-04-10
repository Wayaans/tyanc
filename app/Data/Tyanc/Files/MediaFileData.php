<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Files;

use Illuminate\Support\Number;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaFileData extends Data
{
    /**
     * @param  array<string, mixed>|null  $custom_properties
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public string $file_name,
        public ?string $extension,
        public string $mime_type,
        public string $mime_group,
        public int $size_bytes,
        public string $size_human,
        public bool $is_image,
        public bool $is_previewable,
        public ?string $preview_url,
        public string $url,
        public string $download_url,
        public string $collection_name,
        public ?string $uploaded_by_id,
        public ?string $uploaded_by_name,
        public ?array $custom_properties,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Media $media): self
    {
        $mimeType = (string) ($media->mime_type ?? 'application/octet-stream');
        $mimeGroup = str_contains($mimeType, '/') ? (string) str($mimeType)->before('/')->value() : 'file';
        $isImage = $mimeGroup === 'image';
        $isPreviewable = $isImage || $mimeType === 'application/pdf';
        $customProperties = $media->custom_properties;

        return new self(
            id: (int) $media->id,
            uuid: (string) $media->uuid,
            name: (string) ($media->name !== '' ? $media->name : $media->file_name),
            file_name: (string) $media->file_name,
            extension: pathinfo((string) $media->file_name, PATHINFO_EXTENSION) ?: null,
            mime_type: $mimeType,
            mime_group: $mimeGroup,
            size_bytes: (int) $media->size,
            size_human: Number::fileSize((int) $media->size),
            is_image: $isImage,
            is_previewable: $isPreviewable,
            preview_url: $isPreviewable ? $media->getUrl() : null,
            url: $media->getUrl(),
            download_url: $media->getUrl(),
            collection_name: (string) $media->collection_name,
            uploaded_by_id: data_get($customProperties, 'uploaded_by_id'),
            uploaded_by_name: data_get($customProperties, 'uploaded_by_name'),
            custom_properties: is_array($customProperties) ? $customProperties : null,
            created_at: $media->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updated_at: $media->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}
