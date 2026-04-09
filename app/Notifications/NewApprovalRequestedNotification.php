<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class NewApprovalRequestedNotification extends Notification
{
    use Queueable;

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
            'kind' => 'approval-request',
            'title' => __('New approval requested'),
            'body' => __('A new approval request requires your review.'),
            'action_label' => __('Open approvals'),
            'action_url' => null,
        ];
    }
}
