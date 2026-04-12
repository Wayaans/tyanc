<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
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
            allowedSorts: ['requested_at', 'reviewed_at', 'status', 'action'],
            allowedFilters: ['search', 'status', 'app_key', 'resource_key', 'action_key'],
            defaultSort: ['-requested_at'],
            allowedColumns: ['subject_name', 'action_label', 'status', 'requested_by_name', 'requested_at', 'reviewed_at'],
        );

        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);

        $query = ApprovalRequest::query()
            ->with([
                'requester',
                'reviewer',
                'cancelledBy',
                'subject',
                'assignments.assignee',
                'rule.steps.role',
            ]);

        if ($scope === 'inbox') {
            throw_if(
                ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approvals', 'viewany')),
                AuthorizationException::class,
            );

            $query->whereHas('assignments', function (Builder $builder) use ($actor): void {
                $builder
                    ->where('assigned_to_id', $actor->id)
                    ->where('status', ApprovalAssignment::StatusPending);
            });
        }

        if ($scope === 'my_requests') {
            $query->where('requested_by_id', $actor->id);
        }

        $requests = QueryBuilder::for(subject: $query, request: $queryRequest)
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('app_key'),
                AllowedFilter::exact('resource_key'),
                AllowedFilter::exact('action_key'),
            )
            ->allowedSorts('requested_at', 'reviewed_at', 'status', 'action')
            ->defaultSort('-requested_at')
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
            'filters' => $this->filters(),
        ];
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

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
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
