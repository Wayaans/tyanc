<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Messaging\ListConversations;
use App\Actions\Tyanc\Messaging\ListMessageContacts;
use App\Actions\Tyanc\Messaging\SendMessage;
use App\Data\Tyanc\Messaging\MessageData;
use App\Http\Requests\Tyanc\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final readonly class MessageController
{
    public function store(
        StoreMessageRequest $request,
        #[CurrentUser] User $user,
        Conversation $conversation,
        SendMessage $action,
        ListConversations $conversations,
        ListMessageContacts $contacts,
    ): RedirectResponse|JsonResponse {
        $message = $action->handle($user, $conversation, $request->validated());
        $payload = [
            ...$conversations->handle($user, (string) $conversation->id),
            'contacts' => $contacts->handle($user),
            'abilities' => [
                'createConversation' => Gate::forUser($user)->allows(PermissionKey::tyanc('messages', 'create')),
                'archiveConversation' => Gate::forUser($user)->allows(PermissionKey::tyanc('messages', 'archive')),
                'deleteConversation' => Gate::forUser($user)->allows(PermissionKey::tyanc('messages', 'delete')),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json([
                ...$payload,
                'message' => MessageData::fromModel($message, $user),
            ], 201);
        }

        return to_route('tyanc.messages.index', [
            'conversation' => (string) $conversation->id,
        ]);
    }
}
