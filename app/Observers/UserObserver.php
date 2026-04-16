<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Tyanc\Files\SyncManagedFiles;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserStatusChangedNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Arr;

final readonly class UserObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private SyncManagedFiles $syncManagedFiles) {}

    public function created(User $user): void
    {
        if ($this->hasAvatar($user->avatar)) {
            $this->syncManagedFiles->handle();
        }
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('avatar')) {
            $this->syncManagedFiles->handle();
        }

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

    private function hasAvatar(mixed $avatar): bool
    {
        return is_string($avatar) && mb_trim($avatar) !== '';
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof UserStatus) {
            return $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
