<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateApp
{
    public function __construct(
        private ExecuteApprovalControlledAction $governedActions,
        private SyncAppPages $pages,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{executed: bool, result: mixed, approval: ApprovalRequest|null, bypassed: bool}
     */
    public function handle(User $actor, App $app, array $attributes): array
    {
        Gate::forUser($actor)->authorize('update', $app);

        $validated = $this->validate($app, $attributes);
        $requestNote = $this->nullableString($attributes['request_note'] ?? null);

        return $this->governedActions->handle(
            actor: $actor,
            permissionName: PermissionKey::tyanc('apps', 'update'),
            subject: $app,
            context: [
                ...$validated,
                'request_note' => $requestNote,
                'changed_fields' => $this->changedFields($app, $validated),
            ],
            definition: [
                'execute' => fn (): App => $this->apply($actor, $app, $validated),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update app'),
                        'subject_label' => $app->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $app->approvalSubjectSnapshot(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function apply(User $actor, App $app, array $validated): App
    {
        $before = AppData::fromModel($app->loadMissing('pages'))->toArray();

        return DB::transaction(function () use ($actor, $app, $validated, $before): App {
            $app->fill([
                'key' => $app->isSystem() ? $app->key : $this->normalizedKey((string) $validated['key']),
                'label' => mb_trim((string) $validated['label']),
                'route_prefix' => $app->isSystem() ? $app->route_prefix : $this->normalizedRoutePrefix((string) $validated['route_prefix']),
                'icon' => mb_trim((string) $validated['icon']),
                'permission_namespace' => $app->isSystem() ? $app->permission_namespace : $this->normalizedKey((string) $validated['permission_namespace']),
                'enabled' => $app->isSystem() ? true : (bool) ($validated['enabled'] ?? $app->enabled),
                'sort_order' => (int) ($validated['sort_order'] ?? $app->sort_order),
                'is_system' => $app->isSystem(),
            ]);
            $app->save();

            if (! $app->isSystem() && is_array($validated['pages'] ?? null)) {
                $pages = array_values(array_filter($validated['pages'], is_array(...)));
                $this->pages->handle($app, $pages);
            } else {
                $this->pages->handle($app);
            }

            $app->load('pages');

            activity('apps')
                ->performedOn($app)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => AppData::fromModel($app)->toArray(),
                ])
                ->log('App updated');

            return $app;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function changedFields(App $app, array $attributes): array
    {
        $app->loadMissing('pages');

        $changedFields = collect();
        $fieldMap = [
            'key' => $app->key,
            'label' => $app->label,
            'route_prefix' => $app->route_prefix,
            'icon' => $app->icon,
            'permission_namespace' => $app->permission_namespace,
            'enabled' => $app->enabled,
            'sort_order' => $app->sort_order,
        ];

        foreach ($fieldMap as $field => $currentValue) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            if ($attributes[$field] !== $currentValue) {
                $changedFields->push($field);
            }
        }

        if (is_array($attributes['pages'] ?? null)) {
            $currentPages = $app->pages
                ->map(fn ($page): array => [
                    'key' => $page->key,
                    'label' => $page->label,
                    'route_name' => $page->route_name,
                    'path' => $page->path,
                    'permission_name' => $page->permission_name,
                    'sort_order' => $page->sort_order,
                    'enabled' => $page->enabled,
                    'is_navigation' => $page->is_navigation,
                    'is_system' => $page->is_system,
                ])
                ->values()
                ->all();

            if ($attributes['pages'] !== $currentPages) {
                $changedFields->push('pages');
            }
        }

        return $changedFields->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validate(App $app, array $payload): array
    {
        if (! is_array($payload['pages'] ?? null)) {
            unset($payload['pages']);
        }

        return Validator::make($payload, [
            'key' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique(App::class, 'key')->ignore($app->id)],
            'label' => ['required', 'string', 'max:120'],
            'route_prefix' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9\/-]*$/', Rule::unique(App::class, 'route_prefix')->ignore($app->id)],
            'icon' => ['required', 'string', 'max:80'],
            'permission_namespace' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique(App::class, 'permission_namespace')->ignore($app->id)],
            'enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'pages' => ['sometimes', 'array'],
            'pages.*' => ['array'],
        ])->validate();
    }

    private function normalizedKey(string $value): string
    {
        return mb_strtolower(mb_trim($value));
    }

    private function normalizedRoutePrefix(string $value): string
    {
        return mb_trim(mb_strtolower($value), '/');
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
