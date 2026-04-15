<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Tyanc\Approvals\ApproveRequest;
use App\Actions\Tyanc\Approvals\CancelRequest;
use App\Actions\Tyanc\Approvals\ListApprovalRequestHistory;
use App\Actions\Tyanc\Approvals\ListApprovalRequests;
use App\Actions\Tyanc\Approvals\ReassignApprovalRequest;
use App\Actions\Tyanc\Approvals\RejectRequest;
use App\Actions\Tyanc\Approvals\ResolveApprovers;
use App\Actions\Tyanc\Approvals\ShowApprovalRequest;
use App\Data\Cumpu\Approvals\ApprovalAssignmentData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Http\Requests\Cumpu\ApprovalReassignRequest;
use App\Http\Requests\Tyanc\ApprovalCancelRequest;
use App\Http\Requests\Tyanc\ApprovalDecisionRequest;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApprovalController
{
    public function index(Request $request, #[CurrentUser] User $user, ListApprovalRequests $action): Response|JsonResponse
    {
        $payload = [
            'approvalsTable' => $action->handle($user, $request, 'inbox'),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approvals/Inbox', $payload);
    }

    public function myRequests(Request $request, #[CurrentUser] User $user, ListApprovalRequests $action): Response|JsonResponse
    {
        $payload = [
            'approvalsTable' => $action->handle($user, $request, 'my_requests'),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approvals/MyRequests', $payload);
    }

    public function show(
        Request $request,
        #[CurrentUser] User $user,
        ApprovalRequest $approvalRequest,
        ShowApprovalRequest $showApprovalRequest,
        ListApprovalRequestHistory $history,
        ResolveApprovers $resolveApprovers,
    ): Response|JsonResponse {
        $approvalRequest = $showApprovalRequest->handle($user, $approvalRequest);
        $approval = ApprovalRequestData::fromModel($approvalRequest, $user);

        $payload = [
            'approval' => $approval,
            'assignments' => $approvalRequest->assignments
                ->sortBy('created_at')
                ->values()
                ->map(fn (ApprovalAssignment $assignment): ApprovalAssignmentData => ApprovalAssignmentData::fromModel($assignment))
                ->all(),
            'history' => $history->handle($approvalRequest),
            'reassignOptions' => $this->reassignOptions($approvalRequest, $approval, $resolveApprovers),
            'backLink' => $approvalRequest->requested_by_id === $user->id
                ? [
                    'label' => __('My requests'),
                    'href' => route('cumpu.approvals.my-requests', absolute: false),
                ]
                : [
                    'label' => __('Approvals inbox'),
                    'href' => route('cumpu.approvals.index', absolute: false),
                ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approvals/Show', $payload);
    }

    public function approve(
        ApprovalDecisionRequest $request,
        #[CurrentUser] User $user,
        ApprovalRequest $approvalRequest,
        ApproveRequest $action,
    ): RedirectResponse|JsonResponse {
        $approvalRequest = $action->handle($user, $approvalRequest, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'approval' => ApprovalRequestData::fromModel($approvalRequest, $user),
            ]);
        }

        return back();
    }

    public function reject(
        ApprovalDecisionRequest $request,
        #[CurrentUser] User $user,
        ApprovalRequest $approvalRequest,
        RejectRequest $action,
    ): RedirectResponse|JsonResponse {
        $approvalRequest = $action->handle($user, $approvalRequest, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'approval' => ApprovalRequestData::fromModel($approvalRequest, $user),
            ]);
        }

        return back();
    }

    public function reassign(
        ApprovalReassignRequest $request,
        #[CurrentUser] User $user,
        ApprovalRequest $approvalRequest,
        ReassignApprovalRequest $action,
    ): RedirectResponse|JsonResponse {
        /** @var array{assignment_id: string, assigned_to_id: string, note?: string|null} $validated */
        $validated = $request->validated();
        $assignment = ApprovalAssignment::query()->findOrFail($validated['assignment_id']);
        $approvalRequest = $action->handle(
            $user,
            $approvalRequest,
            $assignment,
            $validated['assigned_to_id'],
            is_string($validated['note'] ?? null) ? $validated['note'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'approval' => ApprovalRequestData::fromModel($approvalRequest, $user),
                'assignments' => $approvalRequest->assignments
                    ->sortBy('created_at')
                    ->values()
                    ->map(fn (ApprovalAssignment $approvalAssignment): ApprovalAssignmentData => ApprovalAssignmentData::fromModel($approvalAssignment))
                    ->all(),
            ]);
        }

        return back();
    }

    public function cancel(
        ApprovalCancelRequest $request,
        #[CurrentUser] User $user,
        ApprovalRequest $approvalRequest,
        CancelRequest $action,
    ): RedirectResponse|JsonResponse {
        $approvalRequest = $action->handle($user, $approvalRequest);

        if ($request->wantsJson()) {
            return response()->json([
                'approval' => ApprovalRequestData::fromModel($approvalRequest, $user),
            ]);
        }

        return back();
    }

    /**
     * @return array<int, array{assignment_id: string, assigned_to_id: string|null, assigned_to_name: string|null, step_label: string|null, step_order: int|null, eligible_assignees: array<int, array{value: string, label: string}>}>
     */
    private function reassignOptions(
        ApprovalRequest $approvalRequest,
        ApprovalRequestData $approval,
        ResolveApprovers $resolveApprovers,
    ): array {
        if (! $approval->can_reassign || ! $approvalRequest->rule instanceof ApprovalRule) {
            return [];
        }

        /** @var Collection<int, ApprovalAssignment>|null $currentStepAssignments */
        $currentStepAssignments = $approvalRequest->assignments
            ->filter(fn (ApprovalAssignment $assignment): bool => $assignment->status === ApprovalAssignment::StatusPending)
            ->sortBy(fn (ApprovalAssignment $assignment): int => $this->stepOrder($assignment) ?? PHP_INT_MAX)
            ->groupBy(fn (ApprovalAssignment $assignment): string => (string) ($this->stepOrder($assignment) ?? ''))
            ->first();

        if ($currentStepAssignments === null || $currentStepAssignments->isEmpty()) {
            return [];
        }

        $assignment = $currentStepAssignments->first();
        $step = $assignment->step;
        $requester = $approvalRequest->requester instanceof User
            ? $approvalRequest->requester
            : null;

        if (! $step instanceof ApprovalRuleStep || ! $requester instanceof User) {
            return [];
        }

        $eligibleAssignees = $resolveApprovers->handle($requester, $approvalRequest->rule, $step)
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->values()
            ->all();

        return [[
            'assignment_id' => (string) $assignment->id,
            'assigned_to_id' => $assignment->assigned_to_id,
            'assigned_to_name' => $assignment->assignee instanceof User ? $assignment->assignee->name : null,
            'step_label' => is_string($assignment->step_label_snapshot) && $assignment->step_label_snapshot !== ''
                ? $assignment->step_label_snapshot
                : $step->label,
            'step_order' => $this->stepOrder($assignment),
            'eligible_assignees' => $eligibleAssignees,
        ]];
    }

    private function stepOrder(ApprovalAssignment $assignment): ?int
    {
        if ($assignment->step_order_snapshot !== null) {
            return (int) $assignment->step_order_snapshot;
        }

        $step = $assignment->step;

        if ($step instanceof ApprovalRuleStep) {
            return $step->step_order;
        }

        return null;
    }
}
