<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Messaging;

use App\Data\Auth\UserData;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class MessageData extends Data
{
    public function __construct(
        public string $id,
        public string $conversation_id,
        public string $sender_id,
        public string $sender_name,
        public ?string $sender_avatar,
        public string $body,
        public bool $is_mine,
        public string $created_at,
    ) {}

    public static function fromModel(Message $message, ?User $viewer = null): self
    {
        $message->loadMissing('sender');
        $sender = $message->sender;
        $senderData = UserData::fromModel($sender);

        return new self(
            id: (string) $message->id,
            conversation_id: (string) $message->conversation_id,
            sender_id: (string) $message->sender_id,
            sender_name: $sender->name,
            sender_avatar: $senderData->avatar,
            body: $message->body,
            is_mine: $viewer instanceof User && $viewer->is($sender),
            created_at: $message->created_at instanceof CarbonInterface ? $message->created_at->toIso8601String() : now()->toIso8601String(),
        );
    }
}
