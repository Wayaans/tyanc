<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ListApprovalRules;
use App\Actions\Tyanc\Approvals\ResolveApprovalCapabilityOptions;
use App\Actions\Tyanc\Approvals\SyncApprovalRulesFromSource;
use App\Actions\Tyanc\Approvals\ToggleApprovalRule;
use App\Actions\Tyanc\Approvals\UpdateApprovalRule;
use App\Http\Requests\Cumpu\ToggleApprovalRuleRequest;
use App\Http\Requests\Cumpu\UpdateManagedApprovalRuleRequest;
use App\Models\ApprovalRule;
use App\Models\Role;
use App\Models\User;
use App\Support\Notifications\FlashToast;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApprovalRuleController
{
    public function __construct(
        private ResolveApprovalCapabilityOptions $capabilityOptions,
        private ListApprovalRules $rules,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        $payload = [
            'rules' => $this->rules->handle($user),
            'capabilityOptions' => $this->capabilityOptions->handle(),
            'roles' => Role::query()
                ->orderByDesc('level')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'value' => $role->id,
                    'label' => $role->name,
                    'level' => $role->level,
                ])
                ->values()
                ->all(),
            'abilities' => [
                'manage' => resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::cumpu('approval_rules', 'manage')),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approval-rules/Index', $payload);
    }

    public function sync(
        Request $request,
        #[CurrentUser] User $user,
        SyncApprovalRulesFromSource $action,
    ): RedirectResponse|JsonResponse {
        $summary = $action->handle($user);

        if ($request->wantsJson()) {
            return response()->json([
                'summary' => $summary,
                'rules' => $this->rules->handle($user),
            ]);
        }

        return to_route('cumpu.approval-rules.index')
            ->with('toast', FlashToast::success(__('Approval capabilities synced.'))->toArray());
    }

    public function update(
        UpdateManagedApprovalRuleRequest $request,
        #[CurrentUser] User $user,
        ApprovalRule $approvalRule,
        UpdateApprovalRule $action,
    ): RedirectResponse|JsonResponse {
        $action->handle($user, $approvalRule, array_merge($request->validated(), [
            'app_key' => $approvalRule->app_key,
            'resource_key' => $approvalRule->resource_key,
            'action_key' => $approvalRule->action_key,
            'enabled' => $approvalRule->enabled,
        ]));

        if ($request->wantsJson()) {
            return response()->json(['rules' => $this->rules->handle($user)]);
        }

        return to_route('cumpu.approval-rules.index');
    }

    public function toggle(
        ToggleApprovalRuleRequest $request,
        #[CurrentUser] User $user,
        ApprovalRule $approvalRule,
        ToggleApprovalRule $action,
    ): RedirectResponse|JsonResponse {
        $approvalRule = $action->handle($user, $approvalRule, $request->boolean('enabled'));

        if ($request->wantsJson()) {
            return response()->json([
                'rule' => [
                    'id' => (string) $approvalRule->id,
                    'enabled' => (bool) $approvalRule->enabled,
                ],
            ]);
        }

        return to_route('cumpu.approval-rules.index');
    }
}
