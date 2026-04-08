<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Models\SettingsAsset;
use App\Settings\AppSettings;
use Spatie\LaravelData\Data;

final class AppSettingsData extends Data
{
    public function __construct(
        public string $app_name,
        public ?string $company_legal_name,
        public ?string $app_logo,
        public ?string $app_logo_uuid,
        public ?string $favicon,
        public ?string $favicon_uuid,
        public ?string $login_cover_image,
        public ?string $login_cover_image_uuid,
    ) {}

    public static function fromSettings(AppSettings $settings, SettingsAsset $assetStore): self
    {
        return new self(
            app_name: $settings->app_name,
            company_legal_name: $settings->company_legal_name,
            app_logo: $assetStore->resolveUrl(SettingsAsset::APP_LOGO_COLLECTION),
            app_logo_uuid: $settings->app_logo,
            favicon: $assetStore->resolveUrl(SettingsAsset::FAVICON_COLLECTION),
            favicon_uuid: $settings->favicon,
            login_cover_image: $assetStore->resolveUrl(SettingsAsset::LOGIN_COVER_IMAGE_COLLECTION),
            login_cover_image_uuid: $settings->login_cover_image,
        );
    }
}
