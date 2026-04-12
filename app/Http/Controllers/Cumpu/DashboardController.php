<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\FindOverdueApprovals;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DashboardController
{
    public function show(
        Request $request,
        #[CurrentUser] User $user,
        FindOverdueApprovals $overdueApprovals,
    ): Response|JsonResponse {
        ApprovalRequest::expirePastDueGrants();

        $permissionAccess = resolve(PermissionResourceAccess::class);
        $canViewInbox = $permissionAccess->handle($user, PermissionKey::cumpu('approval_inbox', 'viewany'));
        $canViewMyRequests = $permissionAccess->handle($user, PermissionKey::cumpu('my_requests', 'viewany'));
        $canManageRules = $permissionAccess->handle($user, PermissionKey::cumpu('approval_rules', 'viewany'));
        $canViewAll = $permissionAccess->handle($user, PermissionKey::cumpu('all_approvals', 'viewany'));
        $canViewReports = $permissionAccess->handle($user, PermissionKey::cumpu('reports', 'viewany'));

        $payload = [
            'summary' => [
                'pending_inbox_count' => $canViewInbox
                    ? ApprovalRequest::query()
                        ->whereHas('assignments', fn (Builder $query) => $query
                            ->where('assigned_to_id', $user->id)
                            ->where('status', ApprovalAssignment::StatusPending))
                        ->whereIn('status', ApprovalRequest::activeStatuses())
                        ->count()
                    : 0,
                'my_request_count' => $canViewMyRequests
                    ? ApprovalRequest::query()
                        ->where('requested_by_id', $user->id)
                        ->count()
                    : 0,
                'ready_to_retry_count' => $canViewMyRequests
                    ? ApprovalRequest::query()
                        ->where('requested_by_id', $user->id)
                        ->where('status', ApprovalRequest::StatusApproved)
                        ->whereNull('consumed_at')
                        ->where(fn (Builder $query) => $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now()))
                        ->count()
                    : 0,
                'consumed_count' => $canViewMyRequests
                    ? ApprovalRequest::query()
                        ->where('requested_by_id', $user->id)
                        ->where('status', ApprovalRequest::StatusConsumed)
                        ->count()
                    : 0,
                'expired_count' => $canViewMyRequests
                    ? ApprovalRequest::query()
                        ->where('requested_by_id', $user->id)
                        ->where('status', ApprovalRequest::StatusExpired)
                        ->count()
                    : 0,
                'enabled_rule_count' => $canManageRules
                    ? ApprovalRule::query()->where('enabled', true)->count()
                    : 0,
                'all_pending_count' => $canViewAll
                    ? ApprovalRequest::query()
                        ->whereIn('status', ApprovalRequest::activeStatuses())
                        ->count()
                    : 0,
                'overdue_count' => $canViewReports
                    ? $overdueApprovals->handle()->count()
                    : 0,
            ],
            'abilities' => [
                'viewInbox' => $canViewInbox,
                'viewMyRequests' => $canViewMyRequests,
                'manageRules' => $canManageRules,
                'viewAll' => $canViewAll,
                'viewReports' => $canViewReports,
            ],
            'recentInbox' => $canViewInbox ? $this->recentInbox($user) : [],
            'recentMyRequests' => $canViewMyRequests ? $this->recentMyRequests($user) : [],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/Dashboard', $payload);
    }

    /**
     * @return array<int, ApprovalRequestData>
     */
    private function recentInbox(User $user): array
    {
        return $this->dashboardQuery()
            ->whereHas('assignments', fn (Builder $query) => $query
                ->where('assigned_to_id', $user->id)
                ->where('status', ApprovalAssignment::StatusPending))
            ->whereIn('status', ApprovalRequest::reviewableStatuses())
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->map(fn (ApprovalRequest $approvalRequest): ApprovalRequestData => ApprovalRequestData::fromModel($approvalRequest, $user))
            ->all();
    }

    /**
     * @return array<int, ApprovalRequestData>
     */
    private function recentMyRequests(User $user): array
    {
        return $this->dashboardQuery()
            ->where('requested_by_id', $user->id)
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->map(fn (ApprovalRequest $approvalRequest): ApprovalRequestData => ApprovalRequestData::fromModel($approvalRequest, $user))
            ->all();
    }

    /**
     * @return Builder<ApprovalRequest>
     */
    private function dashboardQuery(): Builder
    {
        return ApprovalRequest::query()->with([
            'requester',
            'reviewer',
            'cancelledBy',
            'subject',
            'rule.steps.role',
            'consumedBy',
            'assignments.assignee',
            'assignments.completedBy',
            'assignments.step.role',
        ]);
    }
}
