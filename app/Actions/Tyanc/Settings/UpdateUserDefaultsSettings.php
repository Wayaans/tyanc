<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Data\Settings\UserDefaultsSettingsData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\UserDefaultsSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateUserDefaultsSettings
{
    public function __construct(private SubmitGovernedAction $governedActions) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(User $user, array $attributes): array
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'update')),
            AuthorizationException::class,
        );

        $validated = $this->validate($attributes);
        $settings = resolve(UserDefaultsSettings::class);
        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $user,
            permissionName: PermissionKey::tyanc('settings', 'update'),
            context: [
                ...$validated,
                'request_note' => $requestNote,
                'settings_section' => 'user_defaults',
                'changed_fields' => $this->changedFields($validated, $settings),
            ],
            definition: [
                'execute' => fn (): UserDefaultsSettings => $this->apply($validated),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update default user settings'),
                        'subject_label' => __('Defaults for new users'),
                    ],
                    'subject_snapshot' => UserDefaultsSettingsData::fromSettings($settings)->toArray(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function apply(array $validated): UserDefaultsSettings
    {
        $settings = resolve(UserDefaultsSettings::class);
        $settings->locale = (string) $validated['locale'];
        $settings->timezone = (string) $validated['timezone'];
        $settings->appearance = (string) $validated['appearance'];
        $settings->save();

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function changedFields(array $attributes, UserDefaultsSettings $settings): array
    {
        $changedFields = collect();

        foreach (['locale', 'timezone', 'appearance'] as $field) {
            if (($attributes[$field] ?? null) !== $settings->{$field}) {
                $changedFields->push($field);
            }
        }

        return $changedFields->values()->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes): array
    {
        return Validator::make($attributes, [
            'locale' => ['required', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
            'timezone' => ['required', 'timezone'],
            'appearance' => ['required', Rule::in(array_keys((array) config('tyanc.appearance_options', [])))],
        ])->validate();
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
