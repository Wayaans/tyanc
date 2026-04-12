<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Apps\SyncAppPages;
use App\Models\App;
use App\Models\AppPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class AppRegistrySeeder extends Seeder
{
    public function run(): void
    {
        Collection::make((array) config('sidebar-menu.apps', []))
            ->each(function (array $config, string $key): void {
                $app = App::query()->firstOrCreate(
                    ['key' => $key],
                    [
                        'label' => (string) ($config['title'] ?? mb_strtoupper($key)),
                        'route_prefix' => $this->routePrefix($key),
                        'icon' => (string) ($config['icon'] ?? 'layout-grid'),
                        'permission_namespace' => $key,
                        'enabled' => true,
                        'sort_order' => $this->sortOrder($key),
                        'is_system' => in_array($key, (array) config('tyanc.reserved_apps', []), true),
                    ],
                );

                $syncAppPages = resolve(SyncAppPages::class);
                $expectedPages = $syncAppPages->defaultsFor($app);

                if ($app->wasRecentlyCreated || $this->shouldSeedPages($app, $key, $expectedPages)) {
                    $syncAppPages->handle($app, $expectedPages);
                }
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

    private function sortOrder(string $key): int
    {
        return match ($key) {
            'tyanc' => 0,
            'cumpu' => 5,
            'demo' => 10,
            default => 100,
        };
    }

    /**
     * @param  list<array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}>  $expectedPages
     */
    private function shouldSeedPages(App $app, string $key, array $expectedPages): bool
    {
        if ($app->route_prefix !== $this->routePrefix($key) || $app->permission_namespace !== $key) {
            return false;
        }

        if ($expectedPages === []) {
            return $app->pages()->doesntExist();
        }

        $existingPages = $app->pages()->get()->keyBy('key');

        return Collection::make($expectedPages)->contains(function (array $page) use ($app, $existingPages): bool {
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
}
