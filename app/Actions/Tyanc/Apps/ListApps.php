<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\AppPage;
use App\Models\User;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Support\Facades\Gate;

final readonly class ListApps
{
    /**
     * @return list<AppData>
     */
    public function handle(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAny', App::class);

        if (App::query()->doesntExist() || AppPage::query()->doesntExist()) {
            resolve(AppRegistrySeeder::class)->run();
        }

        App::query()
            ->whereIn('key', (array) config('tyanc.reserved_apps', []))
            ->update(['is_system' => true]);

        return App::query()
            ->with('pages')
            ->ordered()
            ->get()
            ->map(fn (App $app): AppData => AppData::fromModel($app))
            ->all();
    }
}
