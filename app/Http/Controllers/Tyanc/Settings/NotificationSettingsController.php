<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ResolveApprovalContext;
use App\Actions\Tyanc\Settings\TriggerNotificationChannelTest;
use App\Actions\Tyanc\Settings\UpdateNotificationSettings;
use App\Data\Settings\NotificationSettingsData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\NotificationSettings;
use App\Support\Notifications\FlashToast;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationSettingsController
{
    public function edit(
        Request $request,
        #[CurrentUser] User $user,
        NotificationSettings $settings,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        abort_unless(
            resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'viewany')),
            403,
        );

        $payload = [
            'settings' => NotificationSettingsData::fromSettings($settings),
            'approvalContext' => $approvalContext->handle(
                actor: $user,
                scopeLabel: __('Notification settings'),
                appKey: 'tyanc',
                resourceKey: 'settings',
                actionKeys: ['update'],
                governedActionKeys: ['update'],
            ),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/Notifications', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateNotificationSettings $action): RedirectResponse|JsonResponse
    {
        $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = $action->handle($user, [
            'sonner_enabled' => $request->boolean('sonner_enabled'),
            'email_enabled' => $request->boolean('email_enabled'),
            'reverb_enabled' => $request->boolean('reverb_enabled'),
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

        /** @var NotificationSettings $settings */
        $settings = $submission['result'];

        $payload = [
            'settings' => NotificationSettingsData::fromSettings($settings),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.notifications.edit');
    }

    public function test(Request $request, #[CurrentUser] User $user, TriggerNotificationChannelTest $action): RedirectResponse|JsonResponse
    {
        $channel = $request->input('channel');

        $action->handle($user, $channel);

        $feedback = $this->feedbackForChannel($user, is_string($channel) ? $channel : 'sonner');

        if ($request->wantsJson()) {
            return response()->json($feedback);
        }

        return back()->with('toast', FlashToast::info(
            $feedback['message'],
            $feedback['description'],
        )->toArray());
    }

    /**
     * @return array{message: string, description: string|null}
     */
    private function feedbackForChannel(User $user, string $channel): array
    {
        return match ($channel) {
            'email' => [
                'message' => __('Test email sent.'),
                'description' => __('We sent a test email to :email.', ['email' => $user->email]),
            ],
            'reverb' => [
                'message' => __('Test live notification sent.'),
                'description' => __('Check the notifications menu for the new item.'),
            ],
            default => [
                'message' => __('Sonner test notification'),
                'description' => __('If you can see this toast, Sonner is working.'),
            ],
        };
    }
}
