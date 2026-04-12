<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Messaging\ConversationParticipantData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

final readonly class ListMessageContacts
{
    /**
     * @return array<int, ConversationParticipantData>
     */
    public function handle(User $actor): array
    {
        if (! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('messages', 'viewany'))) {
            return [];
        }

        return User::query()
            ->whereKeyNot($actor->getKey())
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->orderBy('username')
            ->get()
            ->map(fn (User $user): ConversationParticipantData => ConversationParticipantData::fromModel($user))
            ->all();
    }
}
