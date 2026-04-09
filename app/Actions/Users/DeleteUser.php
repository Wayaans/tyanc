<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\UserFormData;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('delete', $user);

        $before = UserFormData::fromModel($user->fresh(['profile', 'roles', 'permissions']))->toArray();

        $user->delete();

        activity('users')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('deleted')
            ->withProperties([
                'old' => $before,
                'attributes' => UserFormData::fromModel($user->fresh(['profile', 'roles', 'permissions']))->toArray(),
            ])
            ->log('User deleted');
    }
}
