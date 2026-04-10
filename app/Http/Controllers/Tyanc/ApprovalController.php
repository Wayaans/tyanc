<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\Approvals\ApproveRequest;
use App\Actions\Tyanc\Approvals\RejectRequest;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Http\Requests\Tyanc\ApprovalDecisionRequest;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final readonly class ApprovalController
{
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
}
