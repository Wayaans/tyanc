<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

final class ConversationData extends Data
{
    /**
     * @param  array<int, ConversationParticipantData>  $participants
     * @param  array<int, MessageData>  $messages
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $subject,
        public int $participant_count,
        public int $message_count,
        public int $unread_count,
        public ?string $last_message_preview,
        public ?string $last_message_at,
        public ?string $last_sender_name,
        public array $participants,
        public array $messages,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(
        Conversation $conversation,
        ?User $viewer = null,
        int $unreadCount = 0,
        bool $includeMessages = false,
    ): self {
        $conversation->loadMissing(['participants', 'latestMessage.sender']);

        if ($includeMessages) {
            $conversation->loadMissing(['messages' => fn ($query) => $query
                ->with('sender')
                ->latest('created_at')
                ->limit(50)]);
        }

        $latestMessage = $conversation->latestMessage;
        $participants = $conversation->participants
            ->sortBy(fn (User $participant): int => $viewer instanceof User && $participant->is($viewer) ? 1 : 0)
            ->values();

        return new self(
            id: (string) $conversation->id,
            title: $conversation->titleFor($viewer),
            subject: $conversation->subject,
            participant_count: $conversation->participants->count(),
            message_count: $conversation->messages_count ?? $conversation->messages()->count(),
            unread_count: $unreadCount,
            last_message_preview: $latestMessage instanceof Message ? self::preview($latestMessage->body) : null,
            last_message_at: $conversation->last_message_at instanceof CarbonInterface
                ? $conversation->last_message_at->toIso8601String()
                : ($latestMessage?->created_at instanceof CarbonInterface ? $latestMessage->created_at->toIso8601String() : null),
            last_sender_name: $latestMessage?->sender?->name,
            participants: $participants
                ->map(fn (User $participant): ConversationParticipantData => ConversationParticipantData::fromModel($participant))
                ->all(),
            messages: $includeMessages
                ? $conversation->messages
                    ->sortBy('created_at')
                    ->values()
                    ->map(fn (Message $message): MessageData => MessageData::fromModel($message, $viewer))
                    ->all()
                : [],
            created_at: $conversation->created_at instanceof CarbonInterface ? $conversation->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $conversation->updated_at instanceof CarbonInterface ? $conversation->updated_at->toIso8601String() : now()->toIso8601String(),
        );
    }

    private static function preview(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(120)
            ->value();
    }
}
