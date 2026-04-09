<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Models\User;
use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateSecuritySettings
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): SecuritySettings
    {
        Gate::forUser($user)->authorize('manage-settings');

        $validated = Validator::make($attributes, [
            'enforce_2fa' => ['required', 'boolean'],
            'session_timeout' => ['required', 'integer', 'min:5', 'max:10080'],
        ])->validate();

        $settings = resolve(SecuritySettings::class);
        $settings->enforce_2fa = (bool) $validated['enforce_2fa'];
        $settings->session_timeout = (int) $validated['session_timeout'];
        $settings->save();

        return $settings;
    }
}
