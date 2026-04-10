<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final readonly class ToggleApp
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, App $app, array $attributes = []): App
    {
        Gate::forUser($actor)->authorize('toggle', $app);

        if ($app->isSystem()) {
            throw new AuthorizationException(__('System apps cannot be disabled.'));
        }

        $targetState = array_key_exists('enabled', $attributes)
            ? (bool) $attributes['enabled']
            : ! $app->enabled;

        $before = AppData::fromModel($app->loadMissing('pages'))->toArray();

        $app->forceFill([
            'enabled' => $targetState,
        ])->save();

        $app->load('pages');

        activity('apps')
            ->performedOn($app)
            ->causedBy($actor)
            ->event($targetState ? 'enabled' : 'disabled')
            ->withProperties([
                'old' => $before,
                'attributes' => AppData::fromModel($app)->toArray(),
            ])
            ->log($targetState ? 'App enabled' : 'App disabled');

        return $app;
    }
}
