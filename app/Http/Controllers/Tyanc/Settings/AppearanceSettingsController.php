<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ResolveApprovalContext;
use App\Actions\Tyanc\Settings\UpdateAppearanceSettings;
use App\Data\Settings\AppearanceSettingsData;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Settings\AppearanceSettings;
use App\Support\Permissions\PermissionKey;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AppearanceSettingsController
{
    public function edit(
        Request $request,
        #[CurrentUser] User $user,
        AppearanceSettings $settings,
        ResolveApprovalContext $approvalContext,
    ): Response|JsonResponse {
        abort_unless(
            resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::tyanc('settings', 'viewany')),
            403,
        );

        $payload = [
            'settings' => AppearanceSettingsData::fromSettings($settings),
            'approvalContext' => $approvalContext->handle(
                actor: $user,
                scopeLabel: __('App Appearance settings'),
                appKey: 'tyanc',
                resourceKey: 'settings',
                actionKeys: ['update'],
                governedActionKeys: ['update'],
            ),
            'fontFamilies' => $this->fontFamilies(),
            'sidebarVariants' => $this->sidebarVariants(),
            'spacingDensities' => $this->spacingDensities(),
            'status' => $request->session()->get('status'),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/Appearance', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateAppearanceSettings $action): RedirectResponse|JsonResponse
    {
        $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = $action->handle($user, [
            'primary_color' => $request->string('primary_color')->toString(),
            'secondary_color' => $request->string('secondary_color')->toString(),
            'border_radius' => $request->string('border_radius')->toString(),
            'spacing_density' => $request->input('spacing_density'),
            'font_family' => $request->input('font_family'),
            'sidebar_variant' => $request->input('sidebar_variant'),
            'request_note' => $request->input('request_note'),
        ]);

        if ($submission['approval'] instanceof ApprovalRequest) {
            if ($request->wantsJson()) {
                return response()->json([
                    'executed' => false,
                    'approval' => ApprovalRequestData::fromModel($submission['approval'], $user),
                ], 202);
            }

            return back()->with('status', __('Approval request submitted. Retry the update after it is approved.'));
        }

        /** @var AppearanceSettings $settings */
        $settings = $submission['result'];

        $payload = [
            'settings' => AppearanceSettingsData::fromSettings($settings),
            'fontFamilies' => $this->fontFamilies(),
            'sidebarVariants' => $this->sidebarVariants(),
            'spacingDensities' => $this->spacingDensities(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.appearance.edit');
    }

    /**
     * @return list<array{value: string, label: string, stack: string}>
     */
    private function fontFamilies(): array
    {
        return array_values(collect((array) config('tyanc.font_families', []))
            ->map(fn (array $font, string $value): array => [
                'value' => $value,
                'label' => (string) __((string) ($font['label'] ?? $value)),
                'stack' => (string) ($font['stack'] ?? ''),
            ])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sidebarVariants(): array
    {
        return $this->mapSimpleOptions((array) config('tyanc.sidebar_variants', []));
    }

    /**
     * @return list<array{value: string, label: string, density: float}>
     */
    private function spacingDensities(): array
    {
        return array_values(collect((array) config('tyanc.spacing_densities', []))
            ->map(fn (array $density, string $value): array => [
                'value' => $value,
                'label' => (string) __((string) ($density['label'] ?? $value)),
                'density' => (float) ($density['value'] ?? 1.0),
            ])
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
