<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Messaging\ConversationData;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Query\JoinClause;

final readonly class ListRecentConversations
{
    /**
     * @return array{unread_count: int, recent: array<int, ConversationData>}
     */
    public function handle(?User $actor, int $limit = 6): array
    {
        if (! $actor instanceof User) {
            return [
                'unread_count' => 0,
                'recent' => [],
            ];
        }

        if (! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('messages', 'viewany'))) {
            return [
                'unread_count' => 0,
                'recent' => [],
            ];
        }

        $conversations = Conversation::query()
            ->forParticipant($actor)
            ->with(['participants', 'latestMessage.sender'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $unreadCounts = $this->resolveUnreadCounts(
            $actor,
            $conversations->pluck('id')
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values()
                ->all(),
        );

        return [
            'unread_count' => array_sum($unreadCounts),
            'recent' => $conversations
                ->map(fn (Conversation $conversation): ConversationData => ConversationData::fromModel(
                    conversation: $conversation,
                    viewer: $actor,
                    unreadCount: $unreadCounts[(string) $conversation->id] ?? 0,
                    includeMessages: false,
                ))
                ->all(),
        ];
    }

    /**
     * @param  array<int, string>  $conversationIds
     * @return array<string, int>
     */
    private function resolveUnreadCounts(User $actor, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return Message::query()
            ->selectRaw('messages.conversation_id, count(messages.id) as unread_count')
            ->join('conversation_user as conversation_memberships', function (JoinClause $join) use ($actor): void {
                $join->on('conversation_memberships.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_memberships.user_id', '=', (string) $actor->id);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->whereNull('conversation_memberships.archived_at')
            ->where('messages.sender_id', '!=', (string) $actor->id)
            ->where(function ($query): void {
                $query->whereNull('conversation_memberships.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_memberships.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->pluck('unread_count', 'messages.conversation_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
