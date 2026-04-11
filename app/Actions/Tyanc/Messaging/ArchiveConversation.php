<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final readonly class ArchiveConversation
{
    public function handle(User $actor, Conversation $conversation, bool $archive = true): void
    {
        Gate::forUser($actor)->authorize(PermissionKey::tyanc('messages', 'archive'));

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
