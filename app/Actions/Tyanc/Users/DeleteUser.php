<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Data\Tyanc\Users\UserFormData;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('delete', $user);

        if ($user->isDeleteProtected()) {
            throw ValidationException::withMessages([
                'user' => __('Reserved users cannot be deleted.'),
            ]);
        }

        $before = UserFormData::fromModel($user->fresh(['roles', 'permissions']))->toArray();

        $user->delete();

        activity('users')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('deleted')
            ->withProperties([
                'old' => $before,
                'attributes' => UserFormData::fromModel($user->fresh(['roles', 'permissions']))->toArray(),
            ])
            ->log('User deleted');
    }
}
