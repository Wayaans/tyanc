<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Approvals\ApprovalReportRowData;
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

final readonly class ListApprovalReports
{
    public function __construct(private FindOverdueApprovals $overdueApprovals) {}

    /**
     * @return array{
     *     rows: list<ApprovalReportRowData>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: array{date_from: string, date_to: string, status: string, app_key: string, escalated: bool, reassigned: bool, overdue: bool},
     *     appOptions: list<array{value: string, label: string}>,
     *     summary: array{total: int, pending: int, in_review: int, approved: int, rejected: int, cancelled: int, overdue: int, escalated: int, reassigned: int}
     * }
     */
    public function handle(User $actor, Request $request): array
    {
        $this->authorizeView($actor);

        $tableQuery = DataTableQueryData::fromRequest(
            request: $request,
            allowedSorts: ['requested_at', 'reviewed_at', 'status', 'app_key'],
            allowedFilters: [
                'search',
                'date_from',
                'date_to',
                'status',
                'app_key',
                'resource_key',
                'action_key',
                'requested_by_id',
                'reviewed_by_id',
                'assigned_to_id',
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
                'reviewed_by_name',
                'requested_at',
                'reviewed_at',
            ],
        );

        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);

        $overdueIds = $this->overdueApprovals->handle()->pluck('id')->all();
        $reports = $this->query($queryRequest, $overdueIds)
            ->paginate(
                perPage: $tableQuery->per_page,
                page: $tableQuery->page,
            )
            ->withQueryString();

        $summaryRows = $this->rows($actor, $request, $overdueIds);

        return [
            'rows' => Collection::make($reports->items())
                ->map(fn (ApprovalRequest $approvalRequest): ApprovalReportRowData => ApprovalReportRowData::fromModel(
                    $approvalRequest,
                    in_array($approvalRequest->id, $overdueIds, true),
                ))
                ->all(),
            'meta' => $this->meta($reports),
            'query' => $tableQuery->withPage($reports->currentPage()),
            'filters' => [
                'date_from' => (string) $request->input('filter.date_from', ''),
                'date_to' => (string) $request->input('filter.date_to', ''),
                'status' => (string) $request->input('filter.status', ''),
                'app_key' => (string) $request->input('filter.app_key', ''),
                'escalated' => in_array((string) $request->input('filter.escalated', ''), ['1', 'yes'], true),
                'reassigned' => in_array((string) $request->input('filter.reassigned', ''), ['1', 'yes'], true),
                'overdue' => in_array((string) $request->input('filter.overdue', ''), ['1', 'yes'], true),
            ],
            'appOptions' => ApprovalRequest::query()
                ->select('app_key')
                ->whereNotNull('app_key')
                ->distinct()
                ->orderBy('app_key')
                ->pluck('app_key')
                ->filter()
                ->map(fn (string $appKey): array => [
                    'value' => $appKey,
                    'label' => $appKey,
                ])
                ->values()
                ->all(),
            'summary' => [
                'total' => $summaryRows->count(),
                'pending' => $summaryRows->where('status', ApprovalRequest::StatusPending)->count(),
                'in_review' => $summaryRows->where('status', ApprovalRequest::StatusInReview)->count(),
                'approved' => $summaryRows->where('status', ApprovalRequest::StatusApproved)->count(),
                'rejected' => $summaryRows->where('status', ApprovalRequest::StatusRejected)->count(),
                'cancelled' => $summaryRows->where('status', ApprovalRequest::StatusCancelled)->count(),
                'overdue' => $summaryRows->where('is_overdue', true)->count(),
                'escalated' => $summaryRows->where('is_escalated', true)->count(),
                'reassigned' => $summaryRows->where('is_reassigned', true)->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, ApprovalReportRowData>
     */
    public function rows(User $actor, Request $request, ?array $overdueIds = null): Collection
    {
        $this->authorizeView($actor);

        $resolvedOverdueIds = $overdueIds ?? $this->overdueApprovals->handle()->pluck('id')->all();

        return $this->query($request, $resolvedOverdueIds)
            ->get()
            ->map(fn (ApprovalRequest $approvalRequest): ApprovalReportRowData => ApprovalReportRowData::fromModel(
                $approvalRequest,
                in_array($approvalRequest->id, $resolvedOverdueIds, true),
            ));
    }

    private function authorizeView(User $actor): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('reports', 'viewany')),
            AuthorizationException::class,
        );
    }

    /**
     * @param  list<string>  $overdueIds
     */
    private function query(Request $request, array $overdueIds): QueryBuilder
    {
        return QueryBuilder::for(
            subject: ApprovalRequest::query()->with([
                'requester',
                'reviewer',
                'rule.steps.role',
                'assignments.assignee',
                'assignments.step.role',
            ]),
            request: $request,
        )
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::callback('date_from', $this->applyDateFromFilter(...)),
                AllowedFilter::callback('date_to', $this->applyDateToFilter(...)),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('app_key'),
                AllowedFilter::exact('resource_key'),
                AllowedFilter::exact('action_key'),
                AllowedFilter::exact('requested_by_id'),
                AllowedFilter::exact('reviewed_by_id'),
                AllowedFilter::exact('rule_id'),
                AllowedFilter::callback('assigned_to_id', $this->applyAssignedToFilter(...)),
                AllowedFilter::callback('aging', $this->applyAgingFilter(...)),
                AllowedFilter::callback('reassigned', $this->applyReassignedFilter(...)),
                AllowedFilter::callback('escalated', $this->applyEscalatedFilter(...)),
                AllowedFilter::callback('overdue', fn (Builder $query, mixed $value): Builder => $this->applyOverdueFilter($query, $value, $overdueIds)),
            )
            ->allowedSorts('requested_at', 'reviewed_at', 'status', 'app_key')
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
                    ->orWhere('email', 'like', sprintf('%%%s%%', $search)))
                ->orWhereHas('reviewer', fn (Builder $reviewerQuery) => $reviewerQuery
                    ->where('name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('email', 'like', sprintf('%%%s%%', $search)));
        });
    }

    private function applyDateFromFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value) || (string) $value === '') {
            return;
        }

        $query->whereDate('requested_at', '>=', (string) $value);
    }

    private function applyDateToFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value) || (string) $value === '') {
            return;
        }

        $query->whereDate('requested_at', '<=', (string) $value);
    }

    private function applyAssignedToFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value) || (string) $value === '') {
            return;
        }

        $query->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
            ->where('assigned_to_id', (string) $value));
    }

    private function applyAgingFilter(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $resolvedValue = (string) $value;

        match ($resolvedValue) {
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
            return $query->whereKeyNot($overdueIds);
        }

        return $query;
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
