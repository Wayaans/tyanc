<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApprovalCancelledNotification extends Notification
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
            kind: 'approval-cancelled',
            title: __('Approval request cancelled'),
            body: __('The request to retry :action for :subject was cancelled.', [
                'action' => $this->actionLabel($this->approvalRequest),
                'subject' => $this->subjectLabel($this->approvalRequest),
            ]),
            approvalRequest: $this->approvalRequest,
        );
    }
}
