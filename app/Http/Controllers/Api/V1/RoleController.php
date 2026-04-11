<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tyanc\Roles\ListRoles;
use App\Data\Api\ErrorData;
use App\Data\Api\PaginatedData;
use App\Data\Tables\DataTableQueryData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class RoleController
{
    public function index(Request $request, #[CurrentUser] User $user, ListRoles $action): JsonResponse
    {
        try {
            $payload = $action->handle(
                actor: $user,
                query: DataTableQueryData::fromRequest(
                    request: $request,
                    allowedSorts: ['name', 'level', 'permission_count', 'user_count', 'created_at'],
                    allowedFilters: ['search', 'reserved'],
                    defaultSort: ['-level', 'name'],
                    allowedColumns: ['name', 'level', 'permission_count', 'user_count', 'created_at'],
                ),
            );
        } catch (AuthorizationException) {
            return response()->json(ErrorData::forbidden(PermissionKey::tyanc('roles', 'viewany')), 403);
        }

        return response()->json(PaginatedData::fromTablePayload($payload));
    }
}
