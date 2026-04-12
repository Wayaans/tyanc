<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Tyanc\Approvals\ListApprovalRequests;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApprovalOverviewController
{
    public function index(Request $request, #[CurrentUser] User $user, ListApprovalRequests $action): Response|JsonResponse
    {
        $approvalsTable = $action->handle($user, $request, 'all');

        $appFilter = collect($approvalsTable['filters'])->firstWhere('id', 'app_key');

        $payload = [
            'approvalsTable' => $approvalsTable,
            'appOptions' => is_array($appFilter) ? ($appFilter['options'] ?? []) : [],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approvals/All', $payload);
    }
}
