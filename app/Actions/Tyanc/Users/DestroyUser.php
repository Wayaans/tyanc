<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Data\Tyanc\Users\UserFormData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class DestroyUser
{
    public function handle(User $actor, User $user): mixed
    {
        Gate::forUser($actor)->authorize('delete', $user);

        if ($user->isDeleteProtected()) {
            throw ValidationException::withMessages([
                'user' => __('Reserved users cannot be deleted.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $user): mixed {
            $user->loadMissing('roles', 'permissions');
            $before = UserFormData::fromModel($user)->toArray();

            $user->delete();

            activity('users')
                ->performedOn($user)
                ->causedBy($actor)
                ->event('deleted')
                ->withProperties([
                    'old' => $before,
                    'attributes' => UserFormData::fromModel($user)->toArray(),
                ])
                ->log('User deleted');

            return null;
        });
    }
}
