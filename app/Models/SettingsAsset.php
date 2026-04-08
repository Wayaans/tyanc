<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SettingsAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class SettingsAsset extends Model implements HasMedia
{
    /** @use HasFactory<SettingsAssetFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const string GLOBAL_BRANDING_KEY = 'global-branding';

    public const string APP_LOGO_COLLECTION = 'app_logo';

    public const string FAVICON_COLLECTION = 'favicon';

    public const string LOGIN_COVER_IMAGE_COLLECTION = 'login_cover_image';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
    ];

    public static function forKey(string $key): self
    {
        return self::query()->firstOrCreate(['key' => $key]);
    }

    public static function resolveForKey(string $key): self
    {
        return self::query()->firstWhere('key', $key) ?? new self(['key' => $key]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::APP_LOGO_COLLECTION)->singleFile();
        $this->addMediaCollection(self::FAVICON_COLLECTION)->singleFile();
        $this->addMediaCollection(self::LOGIN_COVER_IMAGE_COLLECTION)->singleFile();
    }

    public function resolveUrl(string $collection): ?string
    {
        $media = $this->getFirstMedia($collection);

        return $media?->getUrl();
    }

    public function resolveUuid(string $collection): ?string
    {
        return $this->getFirstMedia($collection)?->uuid;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'int',
            'key' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
