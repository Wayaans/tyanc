<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Data\Settings\SecuritySettingsData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateSecuritySettings
{
    public function __construct(private ExecuteApprovalControlledAction $governedActions) {}

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
        $settings = resolve(SecuritySettings::class);
        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $user,
            permissionName: PermissionKey::tyanc('settings', 'update'),
            context: [
                ...$validated,
                'request_note' => $requestNote,
                'settings_section' => 'security',
                'changed_fields' => $this->changedFields($validated, $settings),
            ],
            definition: [
                'execute' => fn (): SecuritySettings => $this->apply($validated),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update security settings'),
                        'subject_label' => __('Security settings'),
                    ],
                    'subject_snapshot' => SecuritySettingsData::fromSettings($settings)->toArray(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function apply(array $validated): SecuritySettings
    {
        $settings = resolve(SecuritySettings::class);
        $settings->enforce_2fa = (bool) $validated['enforce_2fa'];
        $settings->session_timeout = (int) $validated['session_timeout'];
        $settings->save();

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function changedFields(array $attributes, SecuritySettings $settings): array
    {
        $changedFields = collect();

        if ((bool) ($attributes['enforce_2fa'] ?? false) !== $settings->enforce_2fa) {
            $changedFields->push('enforce_2fa');
        }

        if ((int) ($attributes['session_timeout'] ?? $settings->session_timeout) !== $settings->session_timeout) {
            $changedFields->push('session_timeout');
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
            'enforce_2fa' => ['required', 'boolean'],
            'session_timeout' => ['required', 'integer', 'min:5', 'max:10080'],
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
