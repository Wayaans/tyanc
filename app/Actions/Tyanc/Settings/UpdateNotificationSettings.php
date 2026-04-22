<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Data\Settings\NotificationSettingsData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\NotificationSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateNotificationSettings
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
        $settings = resolve(NotificationSettings::class);
        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $user,
            permissionName: PermissionKey::tyanc('settings', 'update'),
            context: [
                ...$validated,
                'request_note' => $requestNote,
                'settings_section' => 'notifications',
                'changed_fields' => $this->changedFields($validated, $settings),
            ],
            definition: [
                'execute' => fn (): NotificationSettings => $this->apply($validated),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update notification settings'),
                        'subject_label' => __('Notification settings'),
                    ],
                    'subject_snapshot' => NotificationSettingsData::fromSettings($settings)->toArray(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function apply(array $validated): NotificationSettings
    {
        $settings = resolve(NotificationSettings::class);
        $settings->sonner_enabled = (bool) $validated['sonner_enabled'];
        $settings->email_enabled = (bool) $validated['email_enabled'];
        $settings->reverb_enabled = (bool) $validated['reverb_enabled'];
        $settings->save();

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function changedFields(array $attributes, NotificationSettings $settings): array
    {
        $changedFields = collect();

        foreach (['sonner_enabled', 'email_enabled', 'reverb_enabled'] as $field) {
            if ((bool) ($attributes[$field] ?? $settings->{$field}) !== $settings->{$field}) {
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
            'sonner_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'reverb_enabled' => ['required', 'boolean'],
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
