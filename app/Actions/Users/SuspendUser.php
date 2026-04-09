<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\UserFormData;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class SuspendUser
{
    public function handle(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('suspend', $user);

        $before = UserFormData::fromModel($user->fresh(['profile', 'roles', 'permissions']))->toArray();

        if ($user->status !== UserStatus::Suspended) {
            $user->forceFill([
                'status' => UserStatus::Suspended,
            ])->save();
        }

        $user->loadMissing('profile', 'roles', 'permissions');

        activity('users')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'old' => $before,
                'attributes' => UserFormData::fromModel($user)->toArray(),
            ])
            ->log('User suspended');

        return $user;
    }
}
