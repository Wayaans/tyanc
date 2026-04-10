<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tyanc\Apps\SyncAppPages;
use App\Models\App;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class AppRegistrySeeder extends Seeder
{
    public function run(): void
    {
        Collection::make((array) config('sidebar-menu.apps', []))
            ->each(function (array $config, string $key): void {
                $app = App::query()->updateOrCreate(
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

                resolve(SyncAppPages::class)->handle($app);
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
            'demo' => 10,
            default => 100,
        };
    }
}
