<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class UserStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $subject,
        private readonly string $previousStatus,
        private readonly string $currentStatus,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'user-status',
            'title' => __('User status updated'),
            'body' => __('Your account status changed from :from to :to.', [
                'from' => __($this->previousStatus),
                'to' => __($this->currentStatus),
            ]),
            'action_label' => __('Review account'),
            'action_url' => route('settings.account.edit', absolute: false),
            'subject_id' => (string) $this->subject->id,
        ];
    }
}
