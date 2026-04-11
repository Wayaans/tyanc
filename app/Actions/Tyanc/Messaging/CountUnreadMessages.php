<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\Message;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Query\JoinClause;

final readonly class CountUnreadMessages
{
    public function handle(?User $actor): int
    {
        if (! $actor instanceof User) {
            return 0;
        }

        if (! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('messages', 'viewany'))) {
            return 0;
        }

        return (int) Message::query()
            ->join('conversation_user as conversation_memberships', function (JoinClause $join) use ($actor): void {
                $join->on('conversation_memberships.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_memberships.user_id', '=', (string) $actor->id);
            })
            ->whereNull('conversation_memberships.archived_at')
            ->where('messages.sender_id', '!=', (string) $actor->id)
            ->where(function ($query): void {
                $query->whereNull('conversation_memberships.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_memberships.last_read_at');
            })
            ->count('messages.id');
    }
}
