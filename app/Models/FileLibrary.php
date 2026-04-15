<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FileLibraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'key',
    'label',
    'is_system',
])]
final class FileLibrary extends Model implements HasMedia
{
    /** @use HasFactory<FileLibraryFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;

    public const string SharedKey = 'tyanc-shared-library';

    public const string FilesCollection = 'library_files';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'label' => 'Tyanc shared library',
        'is_system' => true,
    ];

    public static function shared(): self
    {
        return self::query()->firstOrCreate(
            ['key' => self::SharedKey],
            [
                'label' => 'Tyanc shared library',
                'is_system' => true,
            ],
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FilesCollection);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'key' => 'string',
            'label' => 'string',
            'is_system' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
