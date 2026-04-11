<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Data\Tyanc\Messaging\ConversationParticipantData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\Gate;

final readonly class ListMessageContacts
{
    /**
     * @return list<ConversationParticipantData>
     */
    public function handle(User $actor): array
    {
        if (! Gate::forUser($actor)->allows(PermissionKey::tyanc('messages', 'viewany'))) {
            return [];
        }

        return User::query()
            ->with('profile')
            ->whereKeyNot($actor->getKey())
            ->whereNull('deleted_at')
            ->orderBy('username')
            ->get()
            ->map(fn (User $user): ConversationParticipantData => ConversationParticipantData::fromModel($user))
            ->all();
    }
}
