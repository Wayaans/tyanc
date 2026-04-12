<?php

declare(strict_types=1);

namespace App\Events;

use App\Data\Tyanc\Messaging\MessageData;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class MessageSent implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public Message $message,
    ) {
        $this->dontBroadcastToCurrentUser();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $this->conversation->loadMissing('participants');

        return [
            new PrivateChannel(sprintf('tyanc.conversations.%s', $this->conversation->id)),
            ...$this->conversation->participants
                ->map(fn ($participant): PrivateChannel => new PrivateChannel(sprintf('tyanc.users.%s.messages', $participant->id)))
                ->all(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->conversation->loadMissing('latestMessage.sender');
        $this->message->loadMissing('sender');

        return [
            'conversation' => [
                'id' => (string) $this->conversation->id,
                'last_message_preview' => Str::of($this->message->body)
                    ->replaceMatches('/\s+/', ' ')
                    ->trim()
                    ->limit(120)
                    ->value(),
                'last_message_at' => $this->message->created_at?->toIso8601String(),
                'last_sender_name' => $this->message->sender->name,
            ],
            'message' => MessageData::fromModel($this->message)->toArray(),
        ];
    }
}
