<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Permissions\ResolvePermissionOptions;
use App\Actions\Tyanc\Roles\AssignPermissionsToRole;
use App\Actions\Tyanc\Roles\DeleteRole;
use App\Actions\Tyanc\Roles\ListRoles;
use App\Actions\Tyanc\Roles\StoreRole;
use App\Actions\Tyanc\Roles\UpdateRole;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Rbac\RoleData;
use App\Http\Requests\Tyanc\AssignRolePermissionsRequest;
use App\Http\Requests\Tyanc\StoreRoleRequest;
use App\Http\Requests\Tyanc\UpdateRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class RoleController
{
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        ListRoles $action,
        ResolvePermissionOptions $permissionOptions,
    ): Response|JsonResponse {
        $payload = [
            'rolesTable' => $action->handle(
                actor: $user,
                query: DataTableQueryData::fromRequest(
                    request: $request,
                    allowedSorts: ['name', 'level', 'permission_count', 'user_count', 'created_at'],
                    allowedFilters: ['search', 'reserved'],
                    defaultSort: ['-level', 'name'],
                    allowedColumns: ['name', 'level', 'permission_count', 'user_count', 'created_at'],
                ),
            ),
            'permissionOptions' => $permissionOptions->handle(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/roles/Index', $payload);
    }

    public function store(StoreRoleRequest $request, #[CurrentUser] User $user, StoreRole $action): RedirectResponse|JsonResponse
    {
        $role = $action->handle($user, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'role' => RoleData::fromModel($role),
            ], 201);
        }

        return to_route('tyanc.roles.index');
    }

    public function update(UpdateRoleRequest $request, #[CurrentUser] User $user, Role $role, UpdateRole $action): RedirectResponse|JsonResponse
    {
        $role = $action->handle($user, $role, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'role' => RoleData::fromModel($role),
            ]);
        }

        return to_route('tyanc.roles.index');
    }

    public function assignPermissions(
        AssignRolePermissionsRequest $request,
        #[CurrentUser] User $user,
        Role $role,
        AssignPermissionsToRole $action,
    ): RedirectResponse|JsonResponse {
        $role = $action->handle($user, $role, $request->validated('permissions', []));

        if ($request->wantsJson()) {
            return response()->json([
                'role' => RoleData::fromModel($role),
            ]);
        }

        return to_route('tyanc.roles.index');
    }

    public function destroy(Request $request, #[CurrentUser] User $user, Role $role, DeleteRole $action): RedirectResponse|JsonResponse
    {
        $action->handle($user, $role);

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('tyanc.roles.index');
    }
}
