<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ResolveApprovalContext;
use App\Actions\Tyanc\Settings\UpdateSecuritySettings;
use App\Data\Settings\SecuritySettingsData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\Notifications\FlashToast;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SecuritySettingsController
{
    public function edit(
        Request $request,
        #[CurrentUser] User $user,
        SecuritySettings $settings,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        abort_unless(
            resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'viewany')),
            403,
        );

        $payload = [
            'settings' => SecuritySettingsData::fromSettings($settings),
            'approvalContext' => $approvalContext->handle(
                actor: $user,
                scopeLabel: __('Security settings'),
                appKey: 'tyanc',
                resourceKey: 'settings',
                actionKeys: ['update'],
                governedActionKeys: ['update'],
            ),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/Security', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateSecuritySettings $action): RedirectResponse|JsonResponse
    {
        $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = $action->handle($user, [
            'enforce_2fa' => $request->boolean('enforce_2fa'),
            'session_timeout' => $request->integer('session_timeout'),
            'request_note' => $request->input('request_note'),
        ]);

        if ($submission['approval'] instanceof ApprovalRequest) {
            if ($request->wantsJson()) {
                return response()->json([
                    'executed' => false,
                    'approval' => ApprovalRequestData::fromModel($submission['approval'], $user),
                ], 202);
            }

            return back()->with('toast', FlashToast::success(
                __('Approval request submitted. Retry the update after it is approved.'),
            )->toArray());
        }

        /** @var SecuritySettings $settings */
        $settings = $submission['result'];

        $payload = [
            'settings' => SecuritySettingsData::fromSettings($settings),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.security.edit');
    }
}
