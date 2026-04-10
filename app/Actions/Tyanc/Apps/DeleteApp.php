<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteApp
{
    public function handle(User $actor, App $app): void
    {
        Gate::forUser($actor)->authorize('delete', $app);

        $before = AppData::fromModel($app->loadMissing('pages'))->toArray();

        DB::transaction(function () use ($app): void {
            Permission::query()
                ->where('name', 'like', sprintf('%s.%%', $app->permission_namespace))
                ->delete();

            $app->delete();
        });

        activity('apps')
            ->performedOn($app)
            ->causedBy($actor)
            ->event('deleted')
            ->withProperties([
                'old' => $before,
            ])
            ->log('App deleted');
    }
}
