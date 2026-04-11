<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateUserAppearancePreference
{
    public function handle(User $user, mixed $appearance): UserPreference
    {
        $validated = Validator::make([
            'appearance' => $appearance,
        ], [
            'appearance' => ['required', Rule::in(array_keys((array) config('tyanc.appearance_options', [])))],
        ])->validate();

        return UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['appearance' => (string) $validated['appearance']],
        );
    }
}
