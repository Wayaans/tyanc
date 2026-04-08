<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use App\Settings\UserDefaultsSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateUserDefaultsSettings
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): UserDefaultsSettings
    {
        Gate::forUser($user)->authorize('manage-settings');

        $validated = Validator::make($attributes, [
            'locale' => ['required', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['required', 'timezone'],
            'appearance' => ['required', Rule::in(array_keys((array) config('tyanc.appearance_options', [])))],
        ])->validate();

        $settings = resolve(UserDefaultsSettings::class);
        $settings->locale = (string) $validated['locale'];
        $settings->timezone = (string) $validated['timezone'];
        $settings->appearance = (string) $validated['appearance'];
        $settings->save();

        return $settings;
    }
}
