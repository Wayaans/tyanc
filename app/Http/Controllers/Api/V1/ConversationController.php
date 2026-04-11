<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tyanc\Messaging\ListConversations;
use App\Data\Api\ErrorData;
use App\Data\Api\PaginatedData;
use App\Data\Tables\DataTableQueryData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final readonly class ConversationController
{
    public function __construct(private AppliesTableQuery $tableQuery) {}

    public function index(Request $request, #[CurrentUser] User $user, ListConversations $action): JsonResponse
    {
        try {
            $workspace = $action->handle(
                actor: $user,
                selectedConversationId: $request->string('conversation')->toString() ?: null,
            );
        } catch (AuthorizationException) {
            return response()->json(ErrorData::forbidden(PermissionKey::tyanc('messages', 'viewany')), 403);
        }

        $payload = $this->tableQuery->handle(
            items: Collection::make($workspace['conversations'])->map(fn ($conversation): array => $conversation->toArray()),
            query: DataTableQueryData::fromRequest(
                request: $request,
                allowedSorts: ['title', 'last_message_at', 'unread_count', 'participant_count', 'message_count'],
                allowedFilters: ['search', 'unread'],
                defaultSort: ['-last_message_at', 'title'],
                allowedColumns: ['title', 'last_message_at', 'unread_count', 'participant_count', 'message_count'],
            ),
            sorts: [
                'title' => 'title',
                'last_message_at' => 'last_message_at',
                'unread_count' => 'unread_count',
                'participant_count' => 'participant_count',
                'message_count' => 'message_count',
            ],
            filters: [
                'search' => fn (array $row, mixed $value): bool => ! is_scalar($value)
                    || mb_trim((string) $value) === ''
                    || collect(['title', 'subject', 'last_message_preview', 'last_sender_name'])
                        ->contains(fn (string $key): bool => str_contains(mb_strtolower((string) ($row[$key] ?? '')), mb_strtolower(mb_trim((string) $value)))),
                'unread' => fn (array $row, mixed $value): bool => match ((string) $value) {
                    'only' => (int) ($row['unread_count'] ?? 0) > 0,
                    'read' => (int) ($row['unread_count'] ?? 0) === 0,
                    default => true,
                },
            ],
        );

        return response()->json(PaginatedData::fromTablePayload($payload, [
            'selected_conversation' => $workspace['selectedConversation']?->toArray(),
            'selected_conversation_id' => $workspace['selectedConversationId'],
            'unread_count' => $workspace['unreadCount'],
        ]));
    }
}
