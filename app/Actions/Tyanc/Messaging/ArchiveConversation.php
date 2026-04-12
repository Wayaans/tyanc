<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ArchiveConversation
{
    public function handle(User $actor, Conversation $conversation, bool $archive = true): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('messages', 'archive')),
            AuthorizationException::class,
        );

        throw_if(! $conversation->participants()->whereKey($actor->getKey())->exists(), AuthorizationException::class);

        $conversation->participants()->updateExistingPivot($actor->getKey(), [
            'archived_at' => $archive ? now() : null,
            'updated_at' => now(),
        ]);

        activity('messaging')
            ->performedOn($conversation)
            ->causedBy($actor)
            ->event($archive ? 'archived' : 'restored')
            ->withProperties([
                'conversation_id' => (string) $conversation->id,
                'archived' => $archive,
            ])
            ->log($archive ? 'Conversation archived' : 'Conversation restored');
    }
}
