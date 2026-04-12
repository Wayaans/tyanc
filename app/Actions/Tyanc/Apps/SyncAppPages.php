<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Models\App;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class SyncAppPages
{
    /**
     * @return list<array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}>
     */
    public function defaultsFor(App $app): array
    {
        return $this->configuredPages($app);
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    public function handle(App $app, array $pages = []): App
    {
        if ($pages === []) {
            $pages = $this->defaultsFor($app);
        }

        if ($pages === []) {
            return $app->load('pages');
        }

        $validated = Validator::validate(
            ['pages' => $pages],
            [
                'pages' => ['required', 'array'],
                'pages.*.key' => ['required', 'string', 'max:120'],
                'pages.*.label' => ['required', 'string', 'max:160'],
                'pages.*.route_name' => ['nullable', 'string', 'max:255'],
                'pages.*.path' => ['nullable', 'string', 'max:255'],
                'pages.*.permission_name' => ['nullable', 'string', 'max:255'],
                'pages.*.sort_order' => ['nullable', 'integer', 'min:0'],
                'pages.*.enabled' => ['nullable', 'boolean'],
                'pages.*.is_navigation' => ['nullable', 'boolean'],
                'pages.*.is_system' => ['nullable', 'boolean'],
            ],
        );

        /** @var array<int, array<string, mixed>> $validatedPages */
        $validatedPages = is_array($validated['pages'] ?? null)
            ? array_values(array_filter($validated['pages'], is_array(...)))
            : [];

        /** @var array<int, array<string, mixed>> $normalizedPages */
        $normalizedPages = Collection::make($validatedPages)
            ->map(fn (array $page): array => [
                'key' => $this->normalizeKey((string) $page['key']),
                'label' => mb_trim((string) $page['label']),
                'route_name' => $this->nullableString($page['route_name'] ?? null),
                'path' => $this->normalizePath($page['path'] ?? null),
                'permission_name' => $this->nullableString($page['permission_name'] ?? null),
                'sort_order' => (int) ($page['sort_order'] ?? 0),
                'enabled' => (bool) ($page['enabled'] ?? true),
                'is_navigation' => (bool) ($page['is_navigation'] ?? true),
                'is_system' => (bool) ($page['is_system'] ?? $app->is_system),
            ])
            ->unique('key')
            ->values()
            ->all();

        DB::transaction(function () use ($app, $normalizedPages): void {
            foreach ($normalizedPages as $page) {
                $app->pages()->updateOrCreate(
                    ['key' => $page['key']],
                    Arr::except($page, ['key']),
                );
            }

            $app->pages()
                ->whereNotIn('key', Arr::pluck($normalizedPages, 'key'))
                ->delete();
        });

        return $app->load('pages');
    }

    /**
     * @return list<array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}>
     */
    private function configuredPages(App $app): array
    {
        /** @var array<string, mixed>|null $config */
        $config = config(sprintf('sidebar-menu.apps.%s', $app->key));

        if (! is_array($config)) {
            return [];
        }

        $pages = [];
        $sortOrder = 0;

        $registerPage = function (array $item, bool $isNavigation = true) use (&$pages, &$sortOrder, $app): void {
            $routeName = $this->nullableString($item['route'] ?? null);
            $href = $this->nullableString($item['href'] ?? null);
            $label = $this->nullableString($item['title'] ?? null);

            if ($routeName === null && $href === null) {
                return;
            }

            if ($label === null) {
                $label = Str::of((string) ($routeName ?? $href))
                    ->afterLast('.')
                    ->replace(['-', '_'], ' ')
                    ->title()
                    ->value();
            }

            $key = $routeName
                ?? Str::of((string) $href)->trim('/')->replace('/', '_')->replace('-', '_')->value();

            $pages[$key] = [
                'key' => $this->normalizeKey($key),
                'label' => $label,
                'route_name' => $routeName,
                'path' => $this->resolvePath($routeName, $href, $app),
                'permission_name' => $this->nullableString($item['permission'] ?? null),
                'sort_order' => $sortOrder++,
                'enabled' => true,
                'is_navigation' => $isNavigation,
                'is_system' => $app->is_system,
            ];
        };

        $rootRoute = $this->nullableString($config['route'] ?? null);

        if ($rootRoute !== null) {
            $registerPage([
                'title' => 'Dashboard',
                'route' => $rootRoute,
                'permission' => null,
            ]);
        }

        $walk = function (array $items) use (&$walk, $registerPage): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (isset($item['route']) || isset($item['href'])) {
                    $registerPage($item);
                }

                if (is_array($item['children'] ?? null)) {
                    $walk($item['children']);
                }
            }
        };

        $walk(is_array($config['menu'] ?? null) ? $config['menu'] : []);

        return array_values($pages);
    }

    private function resolvePath(?string $routeName, ?string $href, App $app): string
    {
        if (is_string($routeName) && $routeName !== '' && Route::has($routeName)) {
            return route($routeName, absolute: false);
        }

        if (is_string($href) && $href !== '') {
            return $this->normalizePath($href) ?? '/'.mb_trim($app->route_prefix, '/');
        }

        return '/'.mb_trim($app->route_prefix, '/');
    }

    private function normalizeKey(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(['-', '.'], '_')
            ->replace('/', '_')
            ->squish()
            ->trim('_')
            ->value();
    }

    private function normalizePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        if ($value === '') {
            return null;
        }

        return str_starts_with($value, '/') ? $value : '/'.$value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
