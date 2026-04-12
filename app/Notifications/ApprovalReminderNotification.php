<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApprovalReminderNotification extends Notification
{
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
        return [
            'kind' => 'approval-reminder',
            'title' => __('Approval reminder'),
            'body' => __('Approval for :subject is still waiting for your review.', [
                'subject' => $this->subjectLabel(),
            ]),
            'action_label' => __('Open request'),
            'action_url' => route('cumpu.approvals.show', $this->approvalRequest, absolute: false),
        ];
    }

    private function subjectLabel(): string
    {
        $label = data_get($this->approvalRequest->payload, 'subject_label');

        return is_string($label) && $label !== ''
            ? $label
            : __('Approval request');
    }
}
