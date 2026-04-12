<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApprovalApprovedNotification extends Notification
{
    use FormatsApprovalNotificationPayload;
    use Queueable;

    public function __construct(private readonly ApprovalRequest $approvalRequest) {}

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
        $actionLabel = $this->actionLabel($this->approvalRequest);
        $subjectLabel = $this->subjectLabel($this->approvalRequest);

        return $this->approvalNotificationPayload(
            kind: 'approval-approved',
            title: __('Approval grant issued'),
            body: $this->approvalRequest->expires_at === null
                ? __('Your request was approved. Retry :action for :subject once.', [
                    'action' => $actionLabel,
                    'subject' => $subjectLabel,
                ])
                : __('Your request was approved. Retry :action for :subject once before the grant expires.', [
                    'action' => $actionLabel,
                    'subject' => $subjectLabel,
                ]),
            approvalRequest: $this->approvalRequest,
        );
    }
}
