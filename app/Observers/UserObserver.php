<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserStatusChangedNotification;
use Illuminate\Support\Arr;

final class UserObserver
{
    public function updated(User $user): void
    {
        $changes = Arr::except($user->getChanges(), ['updated_at', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);

        if ($changes === []) {
            return;
        }

        $original = Arr::except($user->getOriginal(), ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);

        if (array_key_exists('status', $changes)) {
            $user->notify(new UserStatusChangedNotification(
                subject: $user,
                previousStatus: $this->stringValue($original['status'] ?? ''),
                currentStatus: $this->stringValue($changes['status']),
            ));
        }
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof UserStatus) {
            return $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
