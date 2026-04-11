<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteConversation
{
    public function handle(User $actor, Conversation $conversation): void
    {
        Gate::forUser($actor)->authorize(PermissionKey::tyanc('messages', 'delete'));

        $membership = $conversation->participants()
            ->whereKey($actor->getKey())
            ->first();

        throw_if(! $membership instanceof User, AuthorizationException::class);
        throw_if($membership->pivot?->archived_at === null, AuthorizationException::class);

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
