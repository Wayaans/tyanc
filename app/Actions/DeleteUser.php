<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class DeleteUser
{
    public function handle(User $user): void
    {
        if ($user->isDeleteProtected()) {
            throw ValidationException::withMessages([
                'password' => __('Reserved accounts cannot be deleted.'),
            ]);
        }

        $user->delete();
    }
}
