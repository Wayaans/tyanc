<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
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
                'createConversation' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'create')),
                'archiveConversation' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'archive')),
                'deleteConversation' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'delete')),
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
