<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Messaging\ArchiveConversation;
use App\Actions\Tyanc\Messaging\DeleteConversation;
use App\Actions\Tyanc\Messaging\ListConversations;
use App\Actions\Tyanc\Messaging\ListMessageContacts;
use App\Actions\Tyanc\Messaging\StartConversation;
use App\Data\Tyanc\Messaging\ConversationData;
use App\Http\Requests\Tyanc\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ConversationController
{
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        ListConversations $action,
        ListMessageContacts $contacts,
    ): Response|JsonResponse {
        $selectedConversationId = $request->string('conversation')->toString();
        $payload = [
            ...$action->handle(
                actor: $user,
                selectedConversationId: $selectedConversationId !== '' ? $selectedConversationId : null,
                archived: $this->showArchived($request),
            ),
            'contacts' => $contacts->handle($user),
            'abilities' => $this->abilities($user),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/messages/Index', $payload);
    }

    public function store(
        StoreConversationRequest $request,
        #[CurrentUser] User $user,
        StartConversation $action,
        ListConversations $conversations,
        ListMessageContacts $contacts,
    ): RedirectResponse|JsonResponse {
        $conversation = $action->handle($user, $request->validated());
        $payload = [
            ...$conversations->handle($user, (string) $conversation->id),
            'contacts' => $contacts->handle($user),
            'abilities' => $this->abilities($user),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                ...$payload,
                'conversation' => ConversationData::fromModel($conversation, $user),
            ], 201);
        }

        return to_route('tyanc.messages.index', [
            'conversation' => (string) $conversation->id,
        ]);
    }

    public function archive(
        Request $request,
        #[CurrentUser] User $user,
        Conversation $conversation,
        ArchiveConversation $action,
        ListConversations $conversations,
        ListMessageContacts $contacts,
    ): RedirectResponse|JsonResponse {
        $action->handle($user, $conversation, $request->boolean('archived', true));

        $payload = [
            ...$conversations->handle(
                actor: $user,
                selectedConversationId: (string) $conversation->id,
                archived: $this->showArchived($request),
            ),
            'contacts' => $contacts->handle($user),
            'abilities' => $this->abilities($user),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.messages.index', $this->showArchived($request) ? ['view' => 'archived'] : []);
    }

    public function destroy(
        Request $request,
        #[CurrentUser] User $user,
        Conversation $conversation,
        DeleteConversation $action,
        ListConversations $conversations,
        ListMessageContacts $contacts,
    ): RedirectResponse|JsonResponse {
        $action->handle($user, $conversation);

        $payload = [
            ...$conversations->handle(
                actor: $user,
                archived: $this->showArchived($request),
            ),
            'contacts' => $contacts->handle($user),
            'abilities' => $this->abilities($user),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.messages.index', $this->showArchived($request) ? ['view' => 'archived'] : []);
    }

    /**
     * @return array{createConversation: bool, archiveConversation: bool, deleteConversation: bool}
     */
    private function abilities(User $user): array
    {
        return [
            'createConversation' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'create')),
            'archiveConversation' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'archive')),
            'deleteConversation' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('messages', 'delete')),
        ];
    }

    private function showArchived(Request $request): bool
    {
        return $request->string('view')->toString() === 'archived';
    }
}
