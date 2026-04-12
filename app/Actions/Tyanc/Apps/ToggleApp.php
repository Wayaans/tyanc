<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final readonly class ToggleApp
{
    public function __construct(private SubmitGovernedAction $governedActions) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(User $actor, App $app, array $attributes = []): array
    {
        Gate::forUser($actor)->authorize('toggle', $app);

        if ($app->isSystem()) {
            throw new AuthorizationException(__('System apps cannot be disabled.'));
        }

        $enabled = array_key_exists('enabled', $attributes)
            ? (bool) $attributes['enabled']
            : ! $app->enabled;
        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $actor,
            permissionName: PermissionKey::tyanc('apps', 'toggle'),
            subject: $app,
            context: [
                'enabled' => $enabled,
                'request_note' => $requestNote,
                'changed_fields' => ['enabled'],
            ],
            definition: [
                'execute' => fn (): App => $this->apply($actor, $app, $enabled),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => $enabled ? __('Enable app') : __('Disable app'),
                        'subject_label' => $app->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $app->approvalSubjectSnapshot(),
                ],
            ],
        );
    }

    private function apply(User $actor, App $app, bool $enabled): App
    {
        if ($app->isSystem()) {
            throw new AuthorizationException(__('System apps cannot be disabled.'));
        }

        $before = AppData::fromModel($app->loadMissing('pages'))->toArray();

        $app->forceFill([
            'enabled' => $enabled,
        ])->save();

        $app->load('pages');

        activity('apps')
            ->performedOn($app)
            ->causedBy($actor)
            ->event($enabled ? 'enabled' : 'disabled')
            ->withProperties([
                'old' => $before,
                'attributes' => AppData::fromModel($app)->toArray(),
            ])
            ->log($enabled ? 'App enabled' : 'App disabled');

        return $app;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
