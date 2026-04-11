<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tyanc\Users\ListUsers;
use App\Data\Api\ErrorData;
use App\Data\Api\PaginatedData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class UserController
{
    public function index(Request $request, #[CurrentUser] User $user, ListUsers $action): JsonResponse
    {
        try {
            $payload = $action->handle($user, $request);
        } catch (AuthorizationException) {
            return response()->json(ErrorData::forbidden(PermissionKey::tyanc('users', 'viewany')), 403);
        }

        return response()->json(PaginatedData::fromTablePayload($payload));
    }
}
