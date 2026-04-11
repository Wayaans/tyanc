<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tyanc\Apps\ListApps;
use App\Data\Api\ErrorData;
use App\Data\Api\PaginatedData;
use App\Data\Tables\DataTableQueryData;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final readonly class AppController
{
    public function __construct(private AppliesTableQuery $tableQuery) {}

    public function index(Request $request, #[CurrentUser] User $user, ListApps $action): JsonResponse
    {
        try {
            $apps = $action->handle($user);
        } catch (AuthorizationException) {
            return response()->json(ErrorData::forbidden(PermissionKey::tyanc('apps', 'viewany')), 403);
        }

        $payload = $this->tableQuery->handle(
            items: Collection::make($apps)->map(fn ($app): array => $app->toArray()),
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
                'search' => fn (array $row, mixed $value): bool => ! is_scalar($value)
                    || mb_trim((string) $value) === ''
                    || collect(['label', 'key', 'route_prefix', 'permission_namespace'])
                        ->contains(fn (string $key): bool => str_contains(mb_strtolower((string) ($row[$key] ?? '')), mb_strtolower(mb_trim((string) $value)))),
                'status' => fn (array $row, mixed $value): bool => match ((string) $value) {
                    'enabled' => (bool) ($row['enabled'] ?? false),
                    'disabled' => ! (bool) ($row['enabled'] ?? false),
                    default => true,
                },
                'system' => fn (array $row, mixed $value): bool => match ((string) $value) {
                    'system' => (bool) ($row['is_system'] ?? false),
                    'custom' => ! (bool) ($row['is_system'] ?? false),
                    default => true,
                },
            ],
        );

        return response()->json(PaginatedData::fromTablePayload($payload));
    }
}
