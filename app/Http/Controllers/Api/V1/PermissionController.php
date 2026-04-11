<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tyanc\Permissions\ListPermissions;
use App\Data\Api\ErrorData;
use App\Data\Api\PaginatedData;
use App\Data\Tables\DataTableQueryData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PermissionController
{
    public function index(Request $request, #[CurrentUser] User $user, ListPermissions $action): JsonResponse
    {
        try {
            $payload = $action->handle(
                actor: $user,
                query: DataTableQueryData::fromRequest(
                    request: $request,
                    allowedSorts: ['name', 'app', 'resource', 'action', 'role_count', 'sync_status', 'created_at'],
                    allowedFilters: ['search', 'app', 'resource', 'action', 'role', 'status', 'sync_status'],
                    defaultSort: ['app', 'resource', 'action'],
                    allowedColumns: ['name', 'app', 'resource', 'action', 'role_count', 'sync_status', 'created_at'],
                ),
            );
        } catch (AuthorizationException) {
            return response()->json(ErrorData::forbidden(PermissionKey::tyanc('permissions', 'viewany')), 403);
        }

        return response()->json(PaginatedData::fromTablePayload($payload));
    }
}
