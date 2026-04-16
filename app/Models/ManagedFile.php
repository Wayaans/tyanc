<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ManagedFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $disk
 * @property string $source
 * @property string $app_key
 * @property string $resource_key
 * @property string $folder_path
 * @property string $relative_path
 * @property string $directory_path
 * @property string $name
 * @property string $file_name
 * @property string|null $extension
 * @property string $mime_type
 * @property string $mime_group
 * @property int $size_bytes
 * @property string|null $collection_name
 * @property int|null $media_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property string|null $uploaded_by_id
 * @property string|null $uploaded_by_name
 * @property array<string, mixed>|null $custom_properties
 * @property bool $is_deletable
 * @property CarbonInterface|null $uploaded_at
 * @property CarbonInterface|null $last_modified_at
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'disk',
    'source',
    'app_key',
    'resource_key',
    'folder_path',
    'relative_path',
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
])]
final class ManagedFile extends Model
{
    /** @use HasFactory<ManagedFileFactory> */
    use HasFactory;

    public const string PublicDisk = 'public';

    public const string SourceMediaLibrary = 'media_library';

    public const string SourcePublicDisk = 'public_disk';

    public const string UnassignedAppKey = 'unassigned';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'disk' => 'string',
            'source' => 'string',
            'app_key' => 'string',
            'resource_key' => 'string',
            'folder_path' => 'string',
            'relative_path' => 'string',
            'directory_path' => 'string',
            'name' => 'string',
            'file_name' => 'string',
            'extension' => 'string',
            'mime_type' => 'string',
            'mime_group' => 'string',
            'size_bytes' => 'integer',
            'collection_name' => 'string',
            'media_id' => 'integer',
            'subject_type' => 'string',
            'subject_id' => 'string',
            'subject_label' => 'string',
            'uploaded_by_id' => 'string',
            'uploaded_by_name' => 'string',
            'custom_properties' => 'array',
            'is_deletable' => 'boolean',
            'uploaded_at' => 'datetime',
            'last_modified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
