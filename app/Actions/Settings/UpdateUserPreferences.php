<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateUserPreferences
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): ?UserPreference
    {
        $validated = Validator::make($attributes, [
            'locale' => ['nullable', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['nullable', 'timezone'],
            'appearance' => ['nullable', Rule::in(array_keys((array) config('tyanc.appearance_options', [])))],
            'sidebar_variant' => ['nullable', Rule::in(array_keys((array) config('tyanc.sidebar_variants', [])))],
            'spacing_density' => ['nullable', Rule::in(array_keys((array) config('tyanc.spacing_densities', [])))],
        ])->validate();

        $values = [
            'locale' => $this->nullableString($validated['locale'] ?? null),
            'timezone' => $this->nullableString($validated['timezone'] ?? null),
            'appearance' => $this->nullableString($validated['appearance'] ?? null),
            'sidebar_variant' => $this->nullableString($validated['sidebar_variant'] ?? null),
            'spacing_density' => $this->nullableString($validated['spacing_density'] ?? null),
        ];

        if (collect($values)->filter()->isEmpty()) {
            UserPreference::query()->where('user_id', $user->id)->delete();

            return null;
        }

        return UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            $values,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
