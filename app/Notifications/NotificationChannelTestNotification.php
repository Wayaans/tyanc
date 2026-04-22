<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NotificationChannelTestNotification extends Notification
{
    use Queueable;

    private const string ChannelEmail = 'email';

    private const string ChannelReverb = 'reverb';

    private function __construct(
        private readonly string $channel,
        private readonly string $appName,
    ) {}

    public static function forEmail(string $appName): self
    {
        return new self(self::ChannelEmail, $appName);
    }

    public static function forReverb(string $appName): self
    {
        return new self(self::ChannelReverb, $appName);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->channel) {
            self::ChannelEmail => ['mail'],
            self::ChannelReverb => ['database', 'broadcast'],
            default => [],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Email notification test'))
            ->greeting(__('Hello!'))
            ->line(__('This is a test email from :app.', ['app' => $this->appName]))
            ->line(__('Your email notification channel is working correctly.'));
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'read' => false,
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'notification-test',
            'title' => __('Reverb notification test'),
            'body' => __('This is a live test notification from :app.', ['app' => $this->appName]),
            'action_label' => __('Open notification settings'),
            'action_url' => route('tyanc.settings.notifications.edit', absolute: false),
        ];
    }
}
