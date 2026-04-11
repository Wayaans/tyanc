<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Data\Tyanc\Messaging\ConversationData;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class ListConversations
{
    /**
     * @return array{
     *     conversations: list<ConversationData>,
     *     selectedConversation: ConversationData|null,
     *     selectedConversationId: string|null,
     *     unreadCount: int,
     *     viewMode: 'active'|'archived',
     *     archivedConversationCount: int
     * }
     */
    public function handle(User $actor, ?string $selectedConversationId = null, bool $archived = false): array
    {
        Gate::forUser($actor)->authorize(PermissionKey::tyanc('messages', 'viewany'));

        $conversations = $this->conversationQuery($actor, $archived)
            ->with(['participants.profile', 'latestMessage.sender.profile'])
            ->withCount('messages')
            ->latest('last_message_at')
            ->latest('updated_at')
            ->get();

        $resolvedConversationId = $this->resolveSelectedConversationId($conversations, $selectedConversationId);
        $markConversationAsRead = ! $archived && $this->shouldMarkConversationAsRead($selectedConversationId, $resolvedConversationId);

        if ($markConversationAsRead && is_string($resolvedConversationId)) {
            $this->conversationQuery($actor, false)
                ->find($resolvedConversationId)?->participants()
                ->updateExistingPivot($actor->getKey(), [
                    'last_read_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $unreadCounts = $this->resolveUnreadCounts($actor, $conversations->pluck('id')->all(), $archived);

        if ($markConversationAsRead && is_string($resolvedConversationId)) {
            $unreadCounts[$resolvedConversationId] = 0;
        }

        $selectedConversation = $resolvedConversationId !== null
            ? $this->conversationQuery($actor, $archived)
                ->with([
                    'participants.profile',
                    'latestMessage.sender.profile',
                    'messages' => fn ($query) => $query
                        ->with('sender.profile')
                        ->latest('created_at')
                        ->limit(50),
                ])
                ->withCount('messages')
                ->find($resolvedConversationId)
            : null;

        return [
            'conversations' => $conversations
                ->map(fn (Conversation $conversation): ConversationData => ConversationData::fromModel(
                    conversation: $conversation,
                    viewer: $actor,
                    unreadCount: $unreadCounts[(string) $conversation->id] ?? 0,
                    includeMessages: false,
                ))
                ->all(),
            'selectedConversation' => $selectedConversation instanceof Conversation
                ? ConversationData::fromModel(
                    conversation: $selectedConversation,
                    viewer: $actor,
                    unreadCount: $unreadCounts[(string) $selectedConversation->id] ?? 0,
                    includeMessages: true,
                )
                : null,
            'selectedConversationId' => $resolvedConversationId,
            'unreadCount' => array_sum($unreadCounts),
            'viewMode' => $archived ? 'archived' : 'active',
            'archivedConversationCount' => $this->conversationQuery($actor, true)->count(),
        ];
    }

    /**
     * @param  Collection<int, Conversation>  $conversations
     */
    private function resolveSelectedConversationId(Collection $conversations, ?string $selectedConversationId): ?string
    {
        if (is_string($selectedConversationId) && $selectedConversationId !== '' && $conversations->contains('id', $selectedConversationId)) {
            return $selectedConversationId;
        }

        return $conversations->first()?->id;
    }

    private function shouldMarkConversationAsRead(?string $selectedConversationId, ?string $resolvedConversationId): bool
    {
        if ($resolvedConversationId === null) {
            return false;
        }

        return in_array($selectedConversationId, [null, '', $resolvedConversationId], true);
    }

    /**
     * @param  list<string>  $conversationIds
     * @return array<string, int>
     */
    private function resolveUnreadCounts(User $actor, array $conversationIds, bool $archived): array
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
            ->when(
                $archived,
                fn ($query) => $query->whereNotNull('conversation_memberships.archived_at'),
                fn ($query) => $query->whereNull('conversation_memberships.archived_at'),
            )
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

    private function conversationQuery(User $actor, bool $archived): Builder
    {
        return Conversation::query()->forParticipantState($actor, $archived);
    }
}
