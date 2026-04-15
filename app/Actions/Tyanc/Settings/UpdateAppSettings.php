<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\SettingsAsset;
use App\Models\User;
use App\Settings\AppSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

final readonly class UpdateAppSettings
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): AppSettings
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'update')),
            AuthorizationException::class,
        );

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

        return DB::transaction(function () use ($validated, $user): AppSettings {
            $settings = resolve(AppSettings::class);
            $assetStore = SettingsAsset::forKey(SettingsAsset::GLOBAL_BRANDING_KEY);

            $settings->app_name = (string) $validated['app_name'];
            $settings->company_legal_name = $this->nullableString($validated['company_legal_name'] ?? null);

            $this->syncAsset(
                actor: $user,
                assetStore: $assetStore,
                settings: $settings,
                field: 'app_logo',
                collection: SettingsAsset::APP_LOGO_COLLECTION,
                file: $validated['app_logo'] ?? null,
                remove: (bool) ($validated['remove_app_logo'] ?? false),
            );

            $this->syncAsset(
                actor: $user,
                assetStore: $assetStore,
                settings: $settings,
                field: 'favicon',
                collection: SettingsAsset::FAVICON_COLLECTION,
                file: $validated['favicon'] ?? null,
                remove: (bool) ($validated['remove_favicon'] ?? false),
            );

            $this->syncAsset(
                actor: $user,
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
        User $actor,
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
                ->withCustomProperties([
                    'app_key' => 'tyanc',
                    'resource_key' => 'settings',
                    'folder_path' => match ($collection) {
                        SettingsAsset::APP_LOGO_COLLECTION => 'tyanc/settings/branding/app-logo',
                        SettingsAsset::FAVICON_COLLECTION => 'tyanc/settings/branding/favicon',
                        SettingsAsset::LOGIN_COVER_IMAGE_COLLECTION => 'tyanc/settings/branding/login-cover',
                        default => 'tyanc/settings/assets',
                    },
                    'subject_label' => 'App settings',
                    'uploaded_by_id' => (string) $actor->id,
                    'uploaded_by_name' => $actor->name,
                ])
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
