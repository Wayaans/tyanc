<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApprovalRejectedNotification extends Notification
{
    use BroadcastsDurableNotifications;
    use FormatsApprovalNotificationPayload;
    use Queueable;

    public function __construct(private readonly ApprovalRequest $approvalRequest) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return $this->approvalNotificationPayload(
            kind: 'approval-rejected',
            title: __('Approval request rejected'),
            body: __('Your request to retry :action for :subject was rejected.', [
                'action' => $this->actionLabel($this->approvalRequest),
                'subject' => $this->subjectLabel($this->approvalRequest),
            ]),
            approvalRequest: $this->approvalRequest,
        );
    }
}
