<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class NewApprovalRequestedNotification extends Notification
{
    use BroadcastsDurableNotifications;
    use FormatsApprovalNotificationPayload;
    use Queueable;

    public function __construct(private readonly ?ApprovalRequest $approvalRequest = null) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        if (! $this->approvalRequest instanceof ApprovalRequest) {
            return $this->approvalNotificationPayload(
                kind: 'approval-request',
                title: __('New approval requested'),
                body: __('A new approval request needs your review before the governed action can be retried.'),
            );
        }

        $actionLabel = $this->actionLabel($this->approvalRequest);
        $subjectLabel = $this->subjectLabel($this->approvalRequest);

        return $this->approvalNotificationPayload(
            kind: 'approval-request',
            title: __('New approval requested'),
            body: $this->approvalRequest->requester instanceof User
                ? __('Review this request from :requester. If approved, they can retry :action for :subject once.', [
                    'requester' => $this->approvalRequest->requester->name,
                    'action' => $actionLabel,
                    'subject' => $subjectLabel,
                ])
                : __('Review this request. If approved, the requester can retry :action for :subject once.', [
                    'action' => $actionLabel,
                    'subject' => $subjectLabel,
                ]),
            approvalRequest: $this->approvalRequest,
        );
    }
}
