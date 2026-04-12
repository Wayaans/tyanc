<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DashboardController
{
    public function show(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        $permissionAccess = resolve(PermissionResourceAccess::class);

        $payload = [
            'summary' => [
                'pending_inbox_count' => $permissionAccess->handle($user, PermissionKey::cumpu('approvals', 'viewany'))
                    ? ApprovalRequest::query()
                        ->whereHas('assignments', fn ($query) => $query
                            ->where('assigned_to_id', $user->id)
                            ->where('status', ApprovalAssignment::StatusPending))
                        ->whereIn('status', ApprovalRequest::activeStatuses())
                        ->count()
                    : 0,
                'my_request_count' => $permissionAccess->handle($user, PermissionKey::cumpu('approvals', 'view'))
                    ? ApprovalRequest::query()
                        ->where('requested_by_id', $user->id)
                        ->count()
                    : 0,
                'enabled_rule_count' => $permissionAccess->handle($user, PermissionKey::cumpu('approval_rules', 'viewany'))
                    ? ApprovalRule::query()->where('enabled', true)->count()
                    : 0,
            ],
            'abilities' => [
                'viewInbox' => $permissionAccess->handle($user, PermissionKey::cumpu('approvals', 'viewany')),
                'viewMyRequests' => $permissionAccess->handle($user, PermissionKey::cumpu('approvals', 'view')),
                'manageRules' => $permissionAccess->handle($user, PermissionKey::cumpu('approval_rules', 'viewany')),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/Dashboard', $payload);
    }
}
