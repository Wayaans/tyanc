<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Data\Settings\AppearanceSettingsData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\AppearanceSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateAppearanceSettings
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
        $settings = resolve(AppearanceSettings::class);
        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $user,
            permissionName: PermissionKey::tyanc('settings', 'update'),
            context: [
                ...$validated,
                'request_note' => $requestNote,
                'settings_section' => 'appearance',
                'changed_fields' => $this->changedFields($validated, $settings),
            ],
            definition: [
                'execute' => fn (): AppearanceSettings => $this->apply($validated),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update app appearance settings'),
                        'subject_label' => __('App appearance'),
                    ],
                    'subject_snapshot' => AppearanceSettingsData::fromSettings($settings)->toArray(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function apply(array $validated): AppearanceSettings
    {
        $settings = resolve(AppearanceSettings::class);
        $settings->primary_color = (string) $validated['primary_color'];
        $settings->secondary_color = (string) $validated['secondary_color'];
        $settings->border_radius = (string) $validated['border_radius'];
        $settings->spacing_density = (string) $validated['spacing_density'];
        $settings->font_family = (string) $validated['font_family'];
        $settings->sidebar_variant = (string) $validated['sidebar_variant'];
        $settings->save();

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function changedFields(array $attributes, AppearanceSettings $settings): array
    {
        $changedFields = collect();

        foreach (['primary_color', 'secondary_color', 'border_radius', 'spacing_density', 'font_family', 'sidebar_variant'] as $field) {
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
            'primary_color' => ['required', 'string', 'max:40', 'regex:/^(#[0-9A-Fa-f]{3,8}|oklch\([^)]+\))$/'],
            'secondary_color' => ['required', 'string', 'max:40', 'regex:/^(#[0-9A-Fa-f]{3,8}|oklch\([^)]+\))$/'],
            'border_radius' => ['required', 'string', 'max:20', 'regex:/^\d+(?:\.\d+)?(?:px|rem)$/'],
            'spacing_density' => ['required', Rule::in(array_keys((array) config('tyanc.spacing_densities', [])))],
            'font_family' => ['required', Rule::in(array_keys((array) config('tyanc.font_families', [])))],
            'sidebar_variant' => ['required', Rule::in(array_keys((array) config('tyanc.sidebar_variants', [])))],
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
