<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Actions\Tyanc\Apps\SyncAppPages;
use App\Models\App;
use App\Models\AppPage;
use Illuminate\Support\Collection;

final readonly class SyncConfiguredApps
{
    public function __construct(private SyncAppPages $syncAppPages) {}

    /**
     * @return array{created: int, existing: int, synced: int, skipped: int, apps: list<string>}
     */
    public function handle(): array
    {
        $created = 0;
        $existing = 0;
        $synced = 0;
        $skipped = 0;
        $apps = [];

        Collection::make((array) config('sidebar-menu.apps', []))
            ->each(function (array $config, string $key) use (&$created, &$existing, &$synced, &$skipped, &$apps): void {
                $app = App::query()->firstOrCreate(
                    ['key' => $key],
                    [
                        'label' => (string) ($config['title'] ?? mb_strtoupper($key)),
                        'route_prefix' => $this->routePrefix($key),
                        'icon' => (string) ($config['icon'] ?? 'layout-grid'),
                        'permission_namespace' => $key,
                        'enabled' => true,
                        'sort_order' => $this->sortOrder($key),
                        'is_system' => $this->isConfiguredSystemApp($key),
                    ],
                );

                $apps[] = $key;

                if ($app->wasRecentlyCreated) {
                    $created++;
                } else {
                    $existing++;
                }

                $expectedPages = $this->expectedPagesFor($app);

                if ($app->wasRecentlyCreated || $this->shouldSyncPages($app, $key, $expectedPages)) {
                    $this->syncAppPages->handle($app, $expectedPages);
                    $synced++;

                    return;
                }

                $skipped++;
            });

        return [
            'created' => $created,
            'existing' => $existing,
            'synced' => $synced,
            'skipped' => $skipped,
            'apps' => $apps,
        ];
    }

    /**
     * @return list<string>
     */
    public function configuredAppKeys(): array
    {
        /** @var list<string> $configuredAppKeys */
        $configuredAppKeys = collect(array_keys((array) config('sidebar-menu.apps', [])))
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();

        return $configuredAppKeys;
    }

    /**
     * @return list<string>
     */
    public function systemAppKeys(): array
    {
        /** @var list<string> $systemAppKeys */
        $systemAppKeys = collect((array) config('tyanc.reserved_apps', []))
            ->filter(fn (mixed $key): bool => is_string($key) && in_array($key, $this->configuredAppKeys(), true))
            ->values()
            ->all();

        return $systemAppKeys;
    }

    public function isConfiguredSystemApp(string $key): bool
    {
        return in_array($key, $this->systemAppKeys(), true);
    }

    public function isManagedIdentity(App $app, string $key): bool
    {
        return $app->route_prefix === $this->routePrefix($key)
            && $app->permission_namespace === $key;
    }

    /**
     * @return list<array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}>
     */
    public function expectedPagesFor(App $app): array
    {
        return $this->syncAppPages->defaultsFor($app);
    }

    public function routePrefix(string $key): string
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
    private function shouldSyncPages(App $app, string $key, array $expectedPages): bool
    {
        if (! $this->isManagedIdentity($app, $key)) {
            return false;
        }

        if ($expectedPages === []) {
            return $app->pages()->doesntExist();
        }

        $existingPages = $app->pages()->get()->keyBy('key');

        return Collection::make($expectedPages)->contains(function (array $page) use ($existingPages): bool {
            $existingPage = $existingPages->get($page['key']);

            if (! $existingPage instanceof AppPage) {
                return true;
            }

            return ! $this->pageMatchesExpected($existingPage, $page);
        });
    }

    /**
     * @param  array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}  $page
     */
    private function pageMatchesExpected(AppPage $existingPage, array $page): bool
    {
        return $existingPage->label === $page['label']
            && $existingPage->route_name === ($page['route_name'] ?? null)
            && $existingPage->path === ($page['path'] ?? null)
            && $existingPage->permission_name === ($page['permission_name'] ?? null)
            && $existingPage->sort_order === $page['sort_order']
            && $existingPage->enabled === $page['enabled']
            && $existingPage->is_navigation === $page['is_navigation']
            && $existingPage->is_system === $page['is_system'];
    }
}
