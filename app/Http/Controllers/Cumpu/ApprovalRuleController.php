<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Tyanc\Approvals\DeleteApprovalRule;
use App\Actions\Tyanc\Approvals\ListApprovalRules;
use App\Actions\Tyanc\Approvals\StoreApprovalRule;
use App\Actions\Tyanc\Approvals\UpdateApprovalRule;
use App\Actions\Tyanc\Permissions\ResolvePermissionOptions;
use App\Http\Requests\Cumpu\StoreApprovalRuleRequest;
use App\Http\Requests\Cumpu\UpdateApprovalRuleRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApprovalRuleController
{
    public function __construct(
        private ResolvePermissionOptions $permissionOptions,
        private ListApprovalRules $rules,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        $payload = [
            'rules' => $this->rules->handle($user),
            'permissionOptions' => $this->permissionOptions->handle(),
            'roles' => Role::query()
                ->orderByDesc('level')
                ->orderBy('name')
                ->get(['id', 'name', 'level'])
                ->map(fn (Role $role): array => [
                    'value' => $role->id,
                    'label' => $role->name,
                    'level' => $role->level,
                ])
                ->all(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approval-rules/Index', $payload);
    }

    public function store(
        StoreApprovalRuleRequest $request,
        #[CurrentUser] User $user,
        StoreApprovalRule $action,
    ): RedirectResponse|JsonResponse {
        $approvalRule = $action->handle($user, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'rule' => [
                    'id' => (string) $approvalRule->id,
                ],
            ], 201);
        }

        return to_route('cumpu.approval-rules.index');
    }

    public function update(
        UpdateApprovalRuleRequest $request,
        #[CurrentUser] User $user,
        ApprovalRule $approvalRule,
        UpdateApprovalRule $action,
    ): RedirectResponse|JsonResponse {
        $approvalRule = $action->handle($user, $approvalRule, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'rule' => [
                    'id' => (string) $approvalRule->id,
                ],
            ]);
        }

        return to_route('cumpu.approval-rules.index');
    }

    public function destroy(
        Request $request,
        #[CurrentUser] User $user,
        ApprovalRule $approvalRule,
        DeleteApprovalRule $action,
    ): RedirectResponse|JsonResponse {
        $action->handle($user, $approvalRule);

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('cumpu.approval-rules.index');
    }
}
