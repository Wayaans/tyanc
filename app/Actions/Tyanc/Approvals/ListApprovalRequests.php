<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListApprovalRequests
{
    public function __construct(private FindOverdueApprovals $overdueApprovals) {}

    /**
     * @return array{
     *     rows: list<ApprovalRequestData>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     * }
     */
    public function handle(User $actor, Request $request, string $scope = 'inbox'): array
    {
        $tableQuery = DataTableQueryData::fromRequest(
            request: $request,
            allowedSorts: ['requested_at', 'reviewed_at', 'status', 'action', 'app_key'],
            allowedFilters: [
                'search',
                'status',
                'app_key',
                'resource_key',
                'action_key',
                'requested_by_id',
                'reviewed_by_id',
                'assigned_to_id',
                'assignee',
                'rule_id',
                'aging',
                'reassigned',
                'escalated',
                'overdue',
            ],
            defaultSort: ['-requested_at'],
            allowedColumns: [
                'subject_name',
                'action_label',
                'status',
                'requested_by_name',
                'requested_at',
                'reviewed_at',
            ],
        );

        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);

        $overdueIds = $this->overdueApprovals->handle()->pluck('id')->all();
        $requests = $this->query($actor, $queryRequest, $scope, $overdueIds)
            ->paginate(
                perPage: $tableQuery->per_page,
                page: $tableQuery->page,
            )
            ->withQueryString();

        return [
            'rows' => Collection::make($requests->items())
                ->map(fn (ApprovalRequest $approvalRequest): ApprovalRequestData => ApprovalRequestData::fromModel($approvalRequest, $actor))
                ->all(),
            'meta' => $this->meta($requests),
            'query' => $tableQuery->withPage($requests->currentPage()),
            'filters' => $this->filters($scope),
        ];
    }

    /**
     * @param  list<string>  $overdueIds
     */
    private function query(User $actor, Request $request, string $scope, array $overdueIds): QueryBuilder
    {
        $query = ApprovalRequest::query()
            ->with([
                'requester',
                'reviewer',
                'cancelledBy',
                'subject',
                'rule.steps.role',
                'assignments.assignee',
                'assignments.completedBy',
                'assignments.step.role',
            ]);

        if ($scope === 'inbox' || $scope === 'all') {
            throw_if(
                ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approvals', 'viewany')),
                AuthorizationException::class,
            );
        }

        if ($scope === 'inbox') {
            $query->whereHas('assignments', function (Builder $builder) use ($actor): void {
                $builder
                    ->where('assigned_to_id', $actor->id)
                    ->where('status', ApprovalAssignment::StatusPending);
            });
        }

        if ($scope === 'my_requests') {
            $query->where('requested_by_id', $actor->id);
        }

        return QueryBuilder::for(subject: $query, request: $request)
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('app_key'),
                AllowedFilter::exact('resource_key'),
                AllowedFilter::exact('action_key'),
                AllowedFilter::exact('requested_by_id'),
                AllowedFilter::exact('reviewed_by_id'),
                AllowedFilter::exact('rule_id'),
                AllowedFilter::callback('assigned_to_id', $this->applyAssignedToFilter(...)),
                AllowedFilter::callback('assignee', $this->applyAssigneeSearchFilter(...)),
                AllowedFilter::callback('aging', $this->applyAgingFilter(...)),
                AllowedFilter::callback('reassigned', $this->applyReassignedFilter(...)),
                AllowedFilter::callback('escalated', $this->applyEscalatedFilter(...)),
                AllowedFilter::callback('overdue', fn (Builder $builder, mixed $value): Builder => $this->applyOverdueFilter($builder, $value, $overdueIds)),
            )
            ->allowedSorts('requested_at', 'reviewed_at', 'status', 'action', 'app_key')
            ->defaultSort('-requested_at');
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
                ->where('action', 'like', sprintf('%%%s%%', $search))
                ->orWhere('request_note', 'like', sprintf('%%%s%%', $search))
                ->orWhere('review_note', 'like', sprintf('%%%s%%', $search))
                ->orWhere('payload->subject_label', 'like', sprintf('%%%s%%', $search))
                ->orWhere('payload->action_label', 'like', sprintf('%%%s%%', $search))
                ->orWhereHas('requester', fn (Builder $requesterQuery) => $requesterQuery
                    ->where('name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('email', 'like', sprintf('%%%s%%', $search)));
        });
    }

    private function applyAssignedToFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value) || (string) $value === '') {
            return;
        }

        $query->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
            ->where('assigned_to_id', (string) $value));
    }

    private function applyAssigneeSearchFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $search = mb_trim((string) $value);

        if ($search === '') {
            return;
        }

        $query->whereHas('assignments.assignee', fn (Builder $assigneeQuery) => $assigneeQuery
            ->where('name', 'like', sprintf('%%%s%%', $search))
            ->orWhere('email', 'like', sprintf('%%%s%%', $search)));
    }

    private function applyAgingFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        match ((string) $value) {
            'under_24h' => $query->where('requested_at', '>=', now()->subDay()),
            'one_to_three_days' => $query->whereBetween('requested_at', [now()->subDays(3), now()->subDay()]),
            'over_three_days' => $query->where('requested_at', '<', now()->subDays(3)),
            default => null,
        };
    }

    private function applyReassignedFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        if (in_array((string) $value, ['yes', '1', 'true'], true)) {
            $query->whereNotNull('last_reassigned_at');

            return;
        }

        if (in_array((string) $value, ['no', '0', 'false'], true)) {
            $query->whereNull('last_reassigned_at');
        }
    }

    private function applyEscalatedFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        if (in_array((string) $value, ['yes', '1', 'true'], true)) {
            $query->whereNotNull('escalated_at');

            return;
        }

        if (in_array((string) $value, ['no', '0', 'false'], true)) {
            $query->whereNull('escalated_at');
        }
    }

    /**
     * @param  list<string>  $overdueIds
     */
    private function applyOverdueFilter(Builder $query, mixed $value, array $overdueIds): Builder
    {
        if (! is_scalar($value)) {
            return $query;
        }

        if (in_array((string) $value, ['yes', '1', 'true'], true)) {
            return $overdueIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereKey($overdueIds);
        }

        if (in_array((string) $value, ['no', '0', 'false'], true) && $overdueIds !== []) {
            return $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $overdueIds);
        }

        return $query;
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(string $scope): array
    {
        $filters = [
            [
                'id' => 'search',
                'label' => 'Approvals',
                'type' => 'text',
                'placeholder' => 'Search approvals',
            ],
            [
                'id' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => collect([
                    ApprovalRequest::StatusDraft,
                    ApprovalRequest::StatusPending,
                    ApprovalRequest::StatusInReview,
                    ApprovalRequest::StatusApproved,
                    ApprovalRequest::StatusRejected,
                    ApprovalRequest::StatusCancelled,
                    ApprovalRequest::StatusExpired,
                    ApprovalRequest::StatusSuperseded,
                ])->map(fn (string $status): array => [
                    'label' => $status,
                    'value' => $status,
                ])->values()->all(),
            ],
            [
                'id' => 'app_key',
                'label' => 'App',
                'type' => 'select',
                'options' => ApprovalRequest::query()
                    ->select('app_key')
                    ->whereNotNull('app_key')
                    ->distinct()
                    ->orderBy('app_key')
                    ->pluck('app_key')
                    ->filter()
                    ->map(fn (string $appKey): array => [
                        'label' => $appKey,
                        'value' => $appKey,
                    ])
                    ->values()
                    ->all(),
            ],
            [
                'id' => 'resource_key',
                'label' => 'Resource',
                'type' => 'select',
                'options' => ApprovalRequest::query()
                    ->select('resource_key')
                    ->whereNotNull('resource_key')
                    ->distinct()
                    ->orderBy('resource_key')
                    ->pluck('resource_key')
                    ->filter()
                    ->map(fn (string $resource): array => [
                        'label' => $resource,
                        'value' => $resource,
                    ])
                    ->values()
                    ->all(),
            ],
            [
                'id' => 'action_key',
                'label' => 'Action',
                'type' => 'select',
                'options' => ApprovalRequest::query()
                    ->select('action_key')
                    ->whereNotNull('action_key')
                    ->distinct()
                    ->orderBy('action_key')
                    ->pluck('action_key')
                    ->filter()
                    ->map(fn (string $action): array => [
                        'label' => $action,
                        'value' => $action,
                    ])
                    ->values()
                    ->all(),
            ],
        ];

        if ($scope !== 'all') {
            return $filters;
        }

        return [
            ...$filters,
            [
                'id' => 'requested_by_id',
                'label' => 'Requester',
                'type' => 'select',
                'options' => User::query()
                    ->whereIn('id', ApprovalRequest::query()->whereNotNull('requested_by_id')->pluck('requested_by_id'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $user): array => [
                        'label' => $user->name,
                        'value' => (string) $user->id,
                    ])
                    ->all(),
            ],
            [
                'id' => 'assigned_to_id',
                'label' => 'Approver',
                'type' => 'select',
                'options' => User::query()
                    ->whereIn('id', ApprovalAssignment::query()->pluck('assigned_to_id')->unique()->values())
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $user): array => [
                        'label' => $user->name,
                        'value' => (string) $user->id,
                    ])
                    ->all(),
            ],
            [
                'id' => 'rule_id',
                'label' => 'Rule',
                'type' => 'select',
                'options' => ApprovalRule::query()
                    ->orderBy('app_key')
                    ->orderBy('resource_key')
                    ->orderBy('action_key')
                    ->get(['id', 'app_key', 'resource_key', 'action_key'])
                    ->map(fn (ApprovalRule $rule): array => [
                        'label' => sprintf('%s • %s • %s', $rule->app_key, $rule->resource_key, $rule->action_key),
                        'value' => (string) $rule->id,
                    ])
                    ->all(),
            ],
            [
                'id' => 'aging',
                'label' => 'Aging',
                'type' => 'select',
                'options' => [
                    ['label' => 'Under 24h', 'value' => 'under_24h'],
                    ['label' => '1–3 days', 'value' => 'one_to_three_days'],
                    ['label' => 'Over 3 days', 'value' => 'over_three_days'],
                ],
            ],
            [
                'id' => 'reassigned',
                'label' => 'Reassigned',
                'type' => 'select',
                'options' => [
                    ['label' => 'Yes', 'value' => 'yes'],
                    ['label' => 'No', 'value' => 'no'],
                ],
            ],
            [
                'id' => 'escalated',
                'label' => 'Escalated',
                'type' => 'select',
                'options' => [
                    ['label' => 'Yes', 'value' => 'yes'],
                    ['label' => 'No', 'value' => 'no'],
                ],
            ],
            [
                'id' => 'overdue',
                'label' => 'Overdue',
                'type' => 'select',
                'options' => [
                    ['label' => 'Yes', 'value' => 'yes'],
                    ['label' => 'No', 'value' => 'no'],
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
