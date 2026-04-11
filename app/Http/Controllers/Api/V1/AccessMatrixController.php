<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\ErrorData;
use App\Data\Api\PaginatedData;
use App\Http\Controllers\Tyanc\AccessMatrixController as TyancAccessMatrixController;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AccessMatrixController
{
    public function __construct(private TyancAccessMatrixController $controller) {}

    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $request->headers->set('Accept', 'application/json');
            $response = $this->controller->index($request, $user);

            if (! $response instanceof JsonResponse) {
                return response()->json(
                    ErrorData::forbidden(
                        PermissionKey::tyanc('access_matrix', 'manage'),
                        __('The access matrix endpoint must return JSON.'),
                    ),
                    403,
                );
            }

            $payload = $response->getData(true);
        } catch (AuthorizationException) {
            return response()->json(ErrorData::forbidden(PermissionKey::tyanc('access_matrix', 'manage')), 403);
        }

        /** @var array<string, mixed> $accessMatrix */
        $accessMatrix = $payload['accessMatrix'] ?? [];
        /** @var array<string, mixed> $matrix */
        $matrix = $accessMatrix['matrix'] ?? [];

        return response()->json(PaginatedData::fromTablePayload([
            'rows' => $matrix['rows'] ?? [],
            'meta' => $matrix['meta'] ?? [],
            'query' => $matrix['query'] ?? [],
            'filters' => $matrix['filters'] ?? [],
        ], [
            'selected_role_id' => $accessMatrix['selected_role_id'] ?? null,
            'selected_app_key' => $accessMatrix['selected_app_key'] ?? null,
            'effective_preview' => $accessMatrix['effective_preview'] ?? null,
        ]));
    }
}
