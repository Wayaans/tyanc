<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Models\App;
use App\Models\AppPage;
use Database\Seeders\AppRegistrySeeder;

final readonly class EnsureAppRegistrySeeded
{
    public function handle(): void
    {
        if (! $this->shouldSeed()) {
            return;
        }

        resolve(AppRegistrySeeder::class)->run();
    }

    private function shouldSeed(): bool
    {
        if (App::query()->doesntExist() || AppPage::query()->doesntExist()) {
            return true;
        }

        foreach (array_keys((array) config('sidebar-menu.apps', [])) as $key) {
            $app = App::query()->where('key', $key)->first();

            if (! $app instanceof App) {
                return true;
            }

            $expectedPages = resolve(SyncAppPages::class)->defaultsFor($app);

            if ($this->pagesNeedHealing($app, $key, $expectedPages)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}>  $expectedPages
     */
    private function pagesNeedHealing(App $app, string $key, array $expectedPages): bool
    {
        if ($app->route_prefix !== $this->routePrefix($key) || $app->permission_namespace !== $key) {
            return false;
        }

        if ($expectedPages === []) {
            return $app->pages()->doesntExist();
        }

        $existingPages = $app->pages()->get()->keyBy('key');

        return collect($expectedPages)->contains(function (array $page) use ($app, $existingPages): bool {
            $existingPage = $existingPages->get($page['key']);

            if (! $existingPage instanceof AppPage) {
                return true;
            }

            return $existingPage->label !== $page['label']
                || $existingPage->route_name !== ($page['route_name'] ?? null)
                || $existingPage->path !== ($page['path'] ?? null)
                || $existingPage->permission_name !== ($page['permission_name'] ?? null)
                || $existingPage->sort_order !== $page['sort_order']
                || $existingPage->enabled !== $page['enabled']
                || $existingPage->is_navigation !== $page['is_navigation']
                || $existingPage->is_system !== ($page['is_system'] ?? $app->is_system);
        });
    }

    private function routePrefix(string $key): string
    {
        return match ($key) {
            'tyanc' => mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/'),
            'demo' => mb_trim((string) config('tyanc.demo_path', 'demo'), '/'),
            default => $key,
        };
    }
}
