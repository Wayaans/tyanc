<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApprovalEscalatedNotification extends Notification
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
            kind: 'approval-escalated',
            title: __('Approval escalated'),
            body: __('This request is overdue. If approved, the requester can retry :action for :subject once.', [
                'action' => $this->actionLabel($this->approvalRequest),
                'subject' => $this->subjectLabel($this->approvalRequest),
            ]),
            approvalRequest: $this->approvalRequest,
        );
    }
}
