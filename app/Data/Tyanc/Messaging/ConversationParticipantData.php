<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Messaging;

use App\Data\Auth\UserData;
use App\Models\User;
use Spatie\LaravelData\Data;

final class ConversationParticipantData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public ?string $avatar,
    ) {}

    public static function fromModel(User $user): self
    {
        $data = UserData::fromModel($user);

        return new self(
            id: (string) $user->id,
            name: $user->name,
            username: $user->username,
            avatar: $data->avatar,
        );
    }
}
