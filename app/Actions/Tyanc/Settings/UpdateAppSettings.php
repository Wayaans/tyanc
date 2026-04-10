<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Models\SettingsAsset;
use App\Models\User;
use App\Settings\AppSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

final readonly class UpdateAppSettings
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): AppSettings
    {
        Gate::forUser($user)->authorize(PermissionKey::tyanc('settings', 'manage'));

        $validated = Validator::make($attributes, [
            'app_name' => ['required', 'string', 'max:120'],
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'app_logo' => ['nullable', File::image()->max(2048)],
            'favicon' => ['nullable', File::types(['png', 'ico', 'svg', 'webp'])->max(1024)],
            'login_cover_image' => ['nullable', File::image()->max(4096)],
            'remove_app_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
            'remove_login_cover_image' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($validated): AppSettings {
            $settings = resolve(AppSettings::class);
            $assetStore = SettingsAsset::forKey(SettingsAsset::GLOBAL_BRANDING_KEY);

            $settings->app_name = (string) $validated['app_name'];
            $settings->company_legal_name = $this->nullableString($validated['company_legal_name'] ?? null);

            $this->syncAsset(
                assetStore: $assetStore,
                settings: $settings,
                field: 'app_logo',
                collection: SettingsAsset::APP_LOGO_COLLECTION,
                file: $validated['app_logo'] ?? null,
                remove: (bool) ($validated['remove_app_logo'] ?? false),
            );

            $this->syncAsset(
                assetStore: $assetStore,
                settings: $settings,
                field: 'favicon',
                collection: SettingsAsset::FAVICON_COLLECTION,
                file: $validated['favicon'] ?? null,
                remove: (bool) ($validated['remove_favicon'] ?? false),
            );

            $this->syncAsset(
                assetStore: $assetStore,
                settings: $settings,
                field: 'login_cover_image',
                collection: SettingsAsset::LOGIN_COVER_IMAGE_COLLECTION,
                file: $validated['login_cover_image'] ?? null,
                remove: (bool) ($validated['remove_login_cover_image'] ?? false),
            );

            $settings->save();

            return $settings;
        });
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private function syncAsset(
        SettingsAsset $assetStore,
        AppSettings $settings,
        string $field,
        string $collection,
        mixed $file,
        bool $remove,
    ): void {
        if ($file instanceof UploadedFile) {
            $media = $assetStore
                ->addMedia($file)
                ->toMediaCollection($collection);

            $settings->{$field} = $media->uuid;

            return;
        }

        if (! $remove) {
            return;
        }

        $assetStore->clearMediaCollection($collection);
        $settings->{$field} = null;
    }
}
