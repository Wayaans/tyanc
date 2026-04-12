<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteConversation
{
    public function handle(User $actor, Conversation $conversation): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('messages', 'delete')),
            AuthorizationException::class,
        );

        $membership = $conversation->participants()
            ->whereKey($actor->getKey())
            ->first();

        throw_if(! $membership instanceof User, AuthorizationException::class);

        $membershipPivot = $membership->pivot;

        throw_if($membershipPivot->getAttribute('archived_at') === null, AuthorizationException::class);

        DB::transaction(function () use ($actor, $conversation): void {
            $conversation->participants()->detach($actor->getKey());

            if (! $conversation->participants()->exists()) {
                $conversation->delete();
            }

            activity('messaging')
                ->performedOn($conversation)
                ->causedBy($actor)
                ->event('deleted')
                ->withProperties([
                    'conversation_id' => (string) $conversation->id,
                ])
                ->log('Conversation deleted');
        });
    }
}
