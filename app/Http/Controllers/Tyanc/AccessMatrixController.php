<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Access\ResolveEffectivePermissions;
use App\Actions\Tyanc\Access\SyncAccessMatrix;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Apps\AppData;
use App\Data\Tyanc\Rbac\AccessMatrixData;
use App\Data\Tyanc\Rbac\PermissionData;
use App\Data\Tyanc\Rbac\RoleData;
use App\Http\Requests\Tyanc\SyncAccessMatrixRequest;
use App\Models\App;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AccessMatrixController
{
    public function __construct(
        private AppliesTableQuery $tableQuery,
        private ResolveEffectivePermissions $effectivePermissions,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        Gate::forUser($user)->authorize(PermissionKey::tyanc('access_matrix', 'manage'));

        $payload = [
            'accessMatrix' => $this->payload($request),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/access-matrix/Index', $payload);
    }

    public function update(SyncAccessMatrixRequest $request, #[CurrentUser] User $user, SyncAccessMatrix $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['permission_id'], $validated['role_id'], $validated['granted'])) {
            $role = Role::query()->with('permissions')->findOrFail((int) $validated['role_id']);
            $permission = Permission::query()->findOrFail((int) $validated['permission_id']);

            $permissionNames = $role->permissions
                ->pluck('name')
                ->when(
                    (bool) $validated['granted'],
                    fn (Collection $permissions): Collection => $permissions->push($permission->name),
                    fn (Collection $permissions): Collection => $permissions->reject(fn (string $name): bool => $name === $permission->name),
                )
                ->unique()
                ->values()
                ->all();

            $action->handle($user, $role, $permissionNames);
        } else {
            $role = Role::query()->where('name', $validated['role'])->firstOrFail();
            $action->handle($user, $role, $validated['permissions'] ?? []);
        }

        $payload = [
            'accessMatrix' => $this->payload($request),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.access-matrix.index');
    }

    private function payload(Request $request): AccessMatrixData
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount(['permissions', 'users'])
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()
            ->with('roles')
            ->withCount('roles')
            ->orderBy('name')
            ->get();

        $apps = App::query()
            ->with('pages')
            ->ordered()
            ->get();

        $query = DataTableQueryData::fromRequest(
            request: $request,
            allowedSorts: ['permission', 'app', 'resource', 'action'],
            allowedFilters: ['search', 'app'],
            defaultSort: ['app', 'resource', 'action'],
            allowedColumns: ['permission', 'app', 'resource', 'action'],
        );

        $matrixRows = $permissions
            ->map(function (Permission $permission) use ($roles, $apps): array {
                $permissionData = PermissionData::fromModel($permission);
                $matchingPage = $apps
                    ->flatMap(fn (App $app): Collection => $app->pages->map(fn ($page): array => ['app' => $app, 'page' => $page]))
                    ->first(fn (array $item): bool => $item['page']->permission_name === $permission->name);

                $page = is_array($matchingPage) ? $matchingPage['page'] ?? null : null;
                $app = is_array($matchingPage) ? $matchingPage['app'] ?? null : null;

                $row = [
                    'id' => (int) $permission->id,
                    'permission' => $permission->name,
                    'app' => $permissionData->app,
                    'resource' => $permissionData->resource,
                    'action' => $permissionData->action,
                    'page' => $page?->label,
                    'page_key' => $page?->key,
                    'app_label' => $app?->label,
                ];

                foreach ($roles as $role) {
                    $row[sprintf('role_%d', $role->id)] = $role->hasPermissionTo($permission);
                }

                return $row;
            })
            ->values();

        $matrix = [
            ...$this->tableQuery->handle(
                items: $matrixRows,
                query: $query,
                sorts: [
                    'permission' => 'permission',
                    'app' => 'app',
                    'resource' => 'resource',
                    'action' => 'action',
                ],
                filters: [
                    'search' => fn (array $row, mixed $value): bool => $this->matchesSearch($row, $value),
                    'app' => 'app',
                ],
            ),
            'filters' => $this->filters($permissions),
        ];

        $previewRoleName = $this->resolvePreviewRoleName($request, $roles);

        $effectivePreview = is_string($previewRoleName) && $previewRoleName !== ''
            ? $this->effectivePermissions->handle(roleNames: [$previewRoleName])
            : null;

        return new AccessMatrixData(
            matrix: $matrix,
            roles: $roles->map(fn (Role $role): RoleData => RoleData::fromModel($role))->all(),
            permissions: $permissions->map(fn (Permission $permission): PermissionData => PermissionData::fromModel($permission))->all(),
            apps: $apps->map(fn (App $app): AppData => AppData::fromModel($app))->all(),
            effective_preview: $effectivePreview,
        );
    }

    private function resolvePreviewRoleName(Request $request, Collection $roles): ?string
    {
        if (is_string($request->input('preview_role')) && $request->string('preview_role')->toString() !== '') {
            return $request->string('preview_role')->toString();
        }

        if ($request->filled('role_id')) {
            return $roles->firstWhere('id', (int) $request->integer('role_id'))?->name;
        }

        if (is_string($request->input('role')) && $request->string('role')->toString() !== '') {
            return $request->string('role')->toString();
        }

        return $roles->first()?->name;
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

        return collect(['app', 'resource', 'action', 'permission', 'page'])
            ->contains(fn (string $key): bool => str_contains(mb_strtolower((string) ($row[$key] ?? '')), $search));
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(Collection $permissions): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Access matrix',
                'type' => 'text',
                'placeholder' => 'Search access rules',
            ],
            [
                'id' => 'app',
                'label' => 'App',
                'type' => 'select',
                'options' => $permissions
                    ->map(fn (Permission $permission): string => explode('.', $permission->name)[0])
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(fn (string $app): array => ['label' => $app, 'value' => $app])
                    ->all(),
            ],
        ];
    }
}
