<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\App;
use LogicException;

final class AppObserver
{
    public function updating(App $app): void
    {
        if (! $app->isSystem()) {
            return;
        }

        $protectedAttributes = ['key', 'route_prefix', 'permission_namespace'];

        foreach ($protectedAttributes as $attribute) {
            if ($app->isDirty($attribute)) {
                throw new LogicException(__('Reserved apps cannot change their identity attributes.'));
            }
        }

        if ($app->isDirty('enabled') && ! $app->enabled) {
            throw new LogicException(__('Reserved apps cannot be disabled.'));
        }
    }

    public function deleting(App $app): void
    {
        if ($app->isSystem()) {
            throw new LogicException(__('Reserved apps cannot be deleted.'));
        }
    }
}
