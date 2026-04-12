<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Activity\ActivityLogEntryData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ActivityLogController
{
    public function index(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        Gate::forUser($user)->authorize(PermissionKey::tyanc('activity_log', 'view'));

        $tableQuery = DataTableQueryData::fromRequest(
            request: $request,
            allowedSorts: ['event', 'log_name', 'created_at'],
            allowedFilters: ['search', 'event', 'log_name'],
            defaultSort: ['-created_at'],
            allowedColumns: ['event', 'description', 'subject_name', 'causer_name', 'created_at'],
        );

        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);

        $activities = QueryBuilder::for(
            subject: Activity::query()->with(['subject', 'causer']),
            request: $queryRequest,
        )
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('log_name'),
            )
            ->allowedSorts('event', 'log_name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(
                perPage: $tableQuery->per_page,
                page: $tableQuery->page,
            )
            ->withQueryString();

        $payload = [
            'activitiesTable' => [
                'rows' => Collection::make($activities->items())
                    ->map(fn (Activity $activity): ActivityLogEntryData => ActivityLogEntryData::fromModel($activity))
                    ->all(),
                'meta' => $this->meta($activities),
                'query' => $tableQuery->withPage($activities->currentPage()),
                'filters' => $this->filters(),
            ],
            'approvalRequests' => $this->approvalRequests($user),
            'abilities' => [
                'export' => $this->permissionAccess()->handle($user, PermissionKey::tyanc('activity_log', 'export')),
                'reviewApprovals' => $this->permissionAccess()->handle($user, PermissionKey::cumpu('approvals', 'viewany')),
            ],
            'features' => [
                'exports_enabled' => (bool) config('tyanc.features.exports_enabled', false),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/activity-log/Index', $payload);
    }

    /**
     * @return list<ApprovalRequestData>
     */
    private function approvalRequests(User $actor): array
    {
        if (! $this->permissionAccess()->handle($actor, PermissionKey::cumpu('approvals', 'viewany'))) {
            return [];
        }

        return ApprovalRequest::query()
            ->with(['requester', 'reviewer', 'subject', 'assignments'])
            ->whereHas('assignments', fn ($query) => $query
                ->where('assigned_to_id', $actor->id)
                ->where('status', 'pending'))
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->map(fn (ApprovalRequest $approvalRequest): ApprovalRequestData => ApprovalRequestData::fromModel($approvalRequest, $actor))
            ->all();
    }

    private function permissionAccess(): PermissionResourceAccess
    {
        return resolve(PermissionResourceAccess::class);
    }

    private function applySearch(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $search = mb_trim((string) $value);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('description', 'like', sprintf('%%%s%%', $search))
                ->orWhere('event', 'like', sprintf('%%%s%%', $search))
                ->orWhereHasMorph(
                    'causer',
                    [User::class],
                    fn (Builder $causerQuery) => $causerQuery
                        ->where('username', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('email', 'like', sprintf('%%%s%%', $search)),
                )
                ->orWhereHasMorph(
                    'subject',
                    [User::class],
                    fn (Builder $subjectQuery) => $subjectQuery
                        ->where('username', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('email', 'like', sprintf('%%%s%%', $search)),
                );
        });
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Activity log',
                'type' => 'text',
                'placeholder' => 'Search activity',
            ],
            [
                'id' => 'event',
                'label' => 'Event',
                'type' => 'select',
                'options' => [
                    ['label' => 'created', 'value' => 'created'],
                    ['label' => 'updated', 'value' => 'updated'],
                    ['label' => 'deleted', 'value' => 'deleted'],
                    ['label' => 'login', 'value' => 'login'],
                ],
            ],
            [
                'id' => 'log_name',
                'label' => 'Category',
                'type' => 'select',
                'options' => [
                    ['label' => 'users', 'value' => 'users'],
                    ['label' => 'auth', 'value' => 'auth'],
                ],
            ],
        ];
    }

    /**
     * @return array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool}
     */
    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'has_pages' => $paginator->hasPages(),
        ];
    }
}
