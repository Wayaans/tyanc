<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Conversation $conversation,
        private readonly Message $message,
        private readonly User $sender,
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
        $viewer = $notifiable instanceof User ? $notifiable : null;

        return [
            'kind' => 'message',
            'title' => __('New message'),
            'body' => __(':sender sent a new message in :conversation.', [
                'sender' => $this->sender->name,
                'conversation' => $this->conversation->titleFor($viewer),
            ]),
            'action_label' => __('Open conversation'),
            'action_url' => route('tyanc.messages.index', [
                'conversation' => (string) $this->conversation->id,
            ], absolute: false),
            'message_preview' => Str::limit($this->message->body, 120),
            'conversation_id' => (string) $this->conversation->id,
            'message_id' => (string) $this->message->id,
            'sender_id' => (string) $this->sender->id,
        ];
    }
}
