<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Data\Tyanc\Apps\AppData;
use App\Models\App;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateApp
{
    public function __construct(private SyncAppPages $pages) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, App $app, array $attributes): App
    {
        Gate::forUser($actor)->authorize('update', $app);

        $before = AppData::fromModel($app->loadMissing('pages'))->toArray();

        $validated = Validator::make($attributes, [
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
                $this->pages->handle($app, $validated['pages']);
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

    private function normalizedKey(string $value): string
    {
        return mb_strtolower(mb_trim($value));
    }

    private function normalizedRoutePrefix(string $value): string
    {
        return mb_trim(mb_strtolower($value), '/');
    }
}
