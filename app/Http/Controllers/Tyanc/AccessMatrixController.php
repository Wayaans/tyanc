<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Access\ResolveEffectivePermissions;
use App\Actions\Tyanc\Access\SyncAccessMatrix;
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
    public function __construct(private ResolveEffectivePermissions $effectivePermissions) {}

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

        return to_route('tyanc.access-matrix.index', array_filter([
            'role_id' => $request->input('role_id'),
            'app' => $request->input('app'),
            'preview_role' => $request->input('preview_role'),
        ], fn (mixed $value): bool => is_string($value) ? $value !== '' : $value !== null));
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

        $selectedRoleId = $this->resolveSelectedRoleId($request, $roles);
        $selectedAppKey = $this->resolveSelectedAppKey($request, $apps);

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
                    'app_label' => $app?->label ?? $permissionData->app_label,
                    'resource' => $permissionData->resource,
                    'resource_label' => $permissionData->resource_label,
                    'action' => $permissionData->action,
                    'action_label' => $permissionData->action_label,
                    'page' => $page?->label,
                    'page_key' => $page?->key,
                ];

                foreach ($roles as $role) {
                    $row[sprintf('role_%d', $role->id)] = $role->hasPermissionTo($permission);
                }

                return $row;
            })
            ->when(
                is_string($selectedAppKey) && $selectedAppKey !== '',
                fn (Collection $rows): Collection => $rows->filter(
                    fn (array $row): bool => ($row['app'] ?? null) === $selectedAppKey,
                ),
                fn (Collection $rows): Collection => collect(),
            )
            ->sortBy([
                ['resource_label', 'asc'],
                ['action_label', 'asc'],
                ['permission', 'asc'],
            ])
            ->values();

        $rowCount = $matrixRows->count();

        $matrix = [
            'rows' => $matrixRows->all(),
            'meta' => [
                'total' => $rowCount,
                'from' => $rowCount > 0 ? 1 : null,
                'to' => $rowCount > 0 ? $rowCount : null,
                'page' => 1,
                'per_page' => $rowCount,
                'last_page' => 1,
                'has_pages' => false,
            ],
            'query' => [
                'page' => 1,
                'per_page' => $rowCount,
                'sort' => [],
                'filter' => [],
                'columns' => [],
            ],
            'filters' => $this->filters($apps),
        ];

        $previewRoleName = $this->resolvePreviewRoleName($request, $roles, $selectedRoleId);

        $effectivePreview = is_string($previewRoleName) && $previewRoleName !== ''
            ? $this->effectivePermissions->handle(roleNames: [$previewRoleName])
            : null;

        return new AccessMatrixData(
            matrix: $matrix,
            roles: $roles->map(fn (Role $role): RoleData => RoleData::fromModel($role))->all(),
            permissions: $permissions->map(fn (Permission $permission): PermissionData => PermissionData::fromModel($permission))->all(),
            apps: $apps->map(fn (App $app): AppData => AppData::fromModel($app))->all(),
            selected_role_id: $selectedRoleId,
            selected_app_key: $selectedAppKey,
            effective_preview: $effectivePreview,
        );
    }

    private function resolvePreviewRoleName(Request $request, Collection $roles, ?int $selectedRoleId): ?string
    {
        if (is_string($request->input('preview_role')) && $request->string('preview_role')->toString() !== '') {
            return $request->string('preview_role')->toString();
        }

        if ($selectedRoleId !== null) {
            return $roles->firstWhere('id', $selectedRoleId)?->name;
        }

        if (is_string($request->input('role')) && $request->string('role')->toString() !== '') {
            return $request->string('role')->toString();
        }

        return null;
    }

    private function resolveSelectedRoleId(Request $request, Collection $roles): ?int
    {
        if (! $request->filled('role_id')) {
            return null;
        }

        $roleId = (int) $request->integer('role_id');

        return $roles->contains('id', $roleId) ? $roleId : null;
    }

    private function resolveSelectedAppKey(Request $request, Collection $apps): ?string
    {
        if (! is_string($request->input('app'))) {
            return null;
        }

        $appKey = $request->string('app')->toString();

        if ($appKey === '') {
            return null;
        }

        return $apps->contains('key', $appKey) ? $appKey : null;
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(Collection $apps): array
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
                'options' => $apps
                    ->map(fn (App $app): array => ['label' => $app->label, 'value' => $app->key])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
