<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Apps\DeleteApp;
use App\Actions\Tyanc\Apps\ListApps;
use App\Actions\Tyanc\Apps\RegisterApp;
use App\Actions\Tyanc\Apps\ToggleApp;
use App\Actions\Tyanc\Apps\UpdateApp;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\User;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AppController
{
    public function __construct(private AppliesTableQuery $tableQuery) {}

    public function index(Request $request, #[CurrentUser] User $user, ListApps $action): Response|JsonResponse
    {
        $apps = $action->handle($user);

        $payload = [
            'apps' => $apps,
            'appsTable' => [
                ...$this->tableQuery->handle(
                    items: Collection::make($apps)->map(fn (AppData $app): array => $app->toArray()),
                    query: DataTableQueryData::fromRequest(
                        request: $request,
                        allowedSorts: ['label', 'key', 'route_prefix', 'permission_namespace', 'enabled', 'sort_order'],
                        allowedFilters: ['search', 'status', 'system'],
                        defaultSort: ['sort_order', 'label'],
                        allowedColumns: ['label', 'key', 'route_prefix', 'permission_namespace', 'enabled', 'sort_order'],
                    ),
                    sorts: [
                        'label' => 'label',
                        'key' => 'key',
                        'route_prefix' => 'route_prefix',
                        'permission_namespace' => 'permission_namespace',
                        'enabled' => fn (array $row): int => $row['enabled'] ? 1 : 0,
                        'sort_order' => 'sort_order',
                    ],
                    filters: [
                        'search' => fn (array $row, mixed $value): bool => $this->matchesSearch($row, $value),
                        'status' => fn (array $row, mixed $value): bool => $this->matchesStatus($row, $value),
                        'system' => fn (array $row, mixed $value): bool => $this->matchesSystem($row, $value),
                    ],
                ),
                'filters' => $this->filters(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/apps/Index', $payload);
    }

    public function store(Request $request, #[CurrentUser] User $user, RegisterApp $action): RedirectResponse|JsonResponse
    {
        $app = $action->handle($user, $this->payload($request));

        if ($request->wantsJson()) {
            return response()->json([
                'app' => AppData::fromModel($app),
            ], 201);
        }

        return to_route('tyanc.apps.index');
    }

    public function update(Request $request, #[CurrentUser] User $user, App $app, UpdateApp $action): RedirectResponse|JsonResponse
    {
        $app = $action->handle($user, $app, $this->payload($request));

        if ($request->wantsJson()) {
            return response()->json([
                'app' => AppData::fromModel($app),
            ]);
        }

        return to_route('tyanc.apps.index');
    }

    public function toggle(Request $request, #[CurrentUser] User $user, App $app, ToggleApp $action): RedirectResponse|JsonResponse
    {
        $app = $action->handle($user, $app, [
            'enabled' => $request->boolean('enabled', ! $app->enabled),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'app' => AppData::fromModel($app),
            ]);
        }

        return to_route('tyanc.apps.index');
    }

    public function destroy(Request $request, #[CurrentUser] User $user, App $app, DeleteApp $action): RedirectResponse|JsonResponse
    {
        $action->handle($user, $app);

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('tyanc.apps.index');
    }

    private function matchesSearch(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        $search = mb_strtolower(mb_trim((string) $value));

        if ($search === '') {
            return true;
        }

        return collect(['label', 'key', 'route_prefix', 'permission_namespace'])
            ->contains(fn (string $key): bool => str_contains(mb_strtolower((string) ($row[$key] ?? '')), $search));
    }

    private function matchesStatus(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        return match ((string) $value) {
            'enabled' => (bool) ($row['enabled'] ?? false),
            'disabled' => ! (bool) ($row['enabled'] ?? false),
            default => true,
        };
    }

    private function matchesSystem(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        return match ((string) $value) {
            'system' => (bool) ($row['is_system'] ?? false),
            'custom' => ! (bool) ($row['is_system'] ?? false),
            default => true,
        };
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Apps',
                'type' => 'text',
                'placeholder' => 'Search apps',
            ],
            [
                'id' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => [
                    ['label' => 'All apps', 'value' => 'all'],
                    ['label' => 'Enabled', 'value' => 'enabled'],
                    ['label' => 'Disabled', 'value' => 'disabled'],
                ],
            ],
            [
                'id' => 'system',
                'label' => 'Type',
                'type' => 'select',
                'options' => [
                    ['label' => 'All apps', 'value' => 'all'],
                    ['label' => 'System', 'value' => 'system'],
                    ['label' => 'Custom', 'value' => 'custom'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return [
            'key' => $request->string('key')->toString(),
            'label' => $request->string('label')->toString(),
            'route_prefix' => $request->string('route_prefix')->toString(),
            'icon' => $request->string('icon')->toString(),
            'permission_namespace' => $request->string('permission_namespace')->toString(),
            'enabled' => $request->boolean('enabled', true),
            'sort_order' => $request->integer('sort_order'),
            'pages' => $request->input('pages', []),
        ];
    }
}
