<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ResolveApprovalContext;
use App\Actions\Tyanc\Settings\UpdateUserDefaultsSettings;
use App\Data\Settings\UserDefaultsSettingsData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\UserDefaultsSettings;
use App\Support\Notifications\FlashToast;
use App\Support\Permissions\PermissionKey;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserDefaultsSettingsController
{
    public function edit(
        Request $request,
        #[CurrentUser] User $user,
        UserDefaultsSettings $settings,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        abort_unless(
            resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'viewany')),
            403,
        );

        $payload = [
            'settings' => UserDefaultsSettingsData::fromSettings($settings),
            'approvalContext' => $approvalContext->handle(
                actor: $user,
                scopeLabel: __('Defaults for New Users'),
                appKey: 'tyanc',
                resourceKey: 'settings',
                actionKeys: ['update'],
                governedActionKeys: ['update'],
            ),
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'locales' => $this->locales(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/UserDefaults', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateUserDefaultsSettings $action): RedirectResponse|JsonResponse
    {
        $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = $action->handle($user, [
            'locale' => $request->string('locale')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'appearance' => $request->input('appearance'),
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

        /** @var UserDefaultsSettings $settings */
        $settings = $submission['result'];

        $payload = [
            'settings' => UserDefaultsSettingsData::fromSettings($settings),
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'locales' => $this->locales(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.user-defaults.edit');
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return array_values(Collection::make(array_keys((array) config('tyanc.supported_locales', [])))
            ->push((string) config('app.locale', 'en'))
            ->unique()
            ->map(fn (mixed $locale): string => (string) $locale)
            ->all());
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    private function mapSimpleOptions(array $options): array
    {
        return array_values(Collection::make($options)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => (string) __($label),
            ])
            ->all());
    }
}
