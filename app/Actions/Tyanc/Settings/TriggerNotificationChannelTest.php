<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\User;
use App\Notifications\NotificationChannelTestNotification;
use App\Settings\NotificationSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class TriggerNotificationChannelTest
{
    private const string ChannelSonner = 'sonner';

    private const string ChannelEmail = 'email';

    private const string ChannelReverb = 'reverb';

    /**
     * @return list<string>
     */
    public static function channels(): array
    {
        return [
            self::ChannelSonner,
            self::ChannelEmail,
            self::ChannelReverb,
        ];
    }

    public function handle(User $user, mixed $channel): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'viewany')),
            AuthorizationException::class,
        );

        $validated = Validator::make([
            'channel' => $channel,
        ], [
            'channel' => ['required', Rule::in(self::channels())],
        ])->validate();

        $resolvedChannel = (string) $validated['channel'];
        $settings = resolve(NotificationSettings::class);

        match ($resolvedChannel) {
            self::ChannelSonner => throw_if(
                ! $settings->sonner_enabled,
                ValidationException::withMessages([
                    'channel' => __('Enable Sonner notifications before sending a test toast.'),
                ]),
            ),
            self::ChannelEmail => throw_if(
                ! $settings->email_enabled,
                ValidationException::withMessages([
                    'channel' => __('Enable email notifications before sending a test email.'),
                ]),
            ),
            self::ChannelReverb => throw_if(
                ! $settings->reverb_enabled,
                ValidationException::withMessages([
                    'channel' => __('Enable Reverb notifications before sending a live test notification.'),
                ]),
            ),
            default => null,
        };

        match ($resolvedChannel) {
            self::ChannelSonner => null,
            self::ChannelEmail => $user->notify(NotificationChannelTestNotification::forEmail((string) config('app.name', 'Tyanc'))),
            self::ChannelReverb => $user->notify(NotificationChannelTestNotification::forReverb((string) config('app.name', 'Tyanc'))),
            default => null,
        };
    }
}
