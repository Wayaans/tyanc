<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Permissions\ListPermissions;
use App\Actions\Tyanc\Permissions\ResolvePermissionOptions;
use App\Actions\Tyanc\Permissions\SyncPermissionsFromSource;
use App\Data\Tables\DataTableQueryData;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PermissionController
{
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        ListPermissions $action,
        ResolvePermissionOptions $permissionOptions,
    ): Response|JsonResponse {
        $payload = [
            'permissionsTable' => $action->handle(
                actor: $user,
                query: DataTableQueryData::fromRequest(
                    request: $request,
                    allowedSorts: ['name', 'app', 'resource', 'action', 'role_count', 'sync_status', 'created_at'],
                    allowedFilters: ['search', 'app', 'resource', 'action', 'role', 'status', 'sync_status'],
                    defaultSort: ['app', 'resource', 'action'],
                    allowedColumns: ['name', 'app', 'resource', 'action', 'role_count', 'sync_status', 'created_at'],
                ),
            ),
            'permissionOptions' => $permissionOptions->handle(),
            'canSyncPermissions' => Gate::forUser($user)->allows('sync', Permission::class),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/permissions/Index', $payload);
    }

    public function sync(
        Request $request,
        #[CurrentUser] User $user,
        SyncPermissionsFromSource $action,
    ): RedirectResponse|JsonResponse {
        Gate::forUser($user)->authorize('sync', Permission::class);

        $result = $action->handle($user);
        $payload = [
            'sync' => $result,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.permissions.index');
    }
}
