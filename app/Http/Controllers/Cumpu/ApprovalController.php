<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Tyanc\Approvals\ApproveRequest;
use App\Actions\Tyanc\Approvals\CancelRequest;
use App\Actions\Tyanc\Approvals\ListApprovalRequestHistory;
use App\Actions\Tyanc\Approvals\ListApprovalRequests;
use App\Actions\Tyanc\Approvals\RejectRequest;
use App\Actions\Tyanc\Approvals\ShowApprovalRequest;
use App\Data\Cumpu\Approvals\ApprovalAssignmentData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Http\Requests\Tyanc\ApprovalCancelRequest;
use App\Http\Requests\Tyanc\ApprovalDecisionRequest;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ): Response|JsonResponse {
        $approvalRequest = $showApprovalRequest->handle($user, $approvalRequest);

        $payload = [
            'approval' => ApprovalRequestData::fromModel($approvalRequest, $user),
            'assignments' => $approvalRequest->assignments
                ->sortBy('created_at')
                ->values()
                ->map(fn (ApprovalAssignment $assignment): ApprovalAssignmentData => ApprovalAssignmentData::fromModel($assignment))
                ->all(),
            'history' => $history->handle($approvalRequest),
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
}
