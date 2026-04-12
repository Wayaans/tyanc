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

final readonly class RegisterApp
{
    public function __construct(private SyncAppPages $pages) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes): App
    {
        Gate::forUser($actor)->authorize('create', App::class);

        $validated = Validator::make($attributes, [
            'key' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique(App::class, 'key')],
            'label' => ['required', 'string', 'max:120'],
            'route_prefix' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9\/-]*$/', Rule::unique(App::class, 'route_prefix')],
            'icon' => ['required', 'string', 'max:80'],
            'permission_namespace' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique(App::class, 'permission_namespace')],
            'enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'pages' => ['sometimes', 'array'],
            'pages.*' => ['array'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated): App {
            $app = App::query()->create([
                'key' => $this->normalizedKey((string) $validated['key']),
                'label' => mb_trim((string) $validated['label']),
                'route_prefix' => $this->normalizedRoutePrefix((string) $validated['route_prefix']),
                'icon' => mb_trim((string) $validated['icon']),
                'permission_namespace' => $this->normalizedKey((string) $validated['permission_namespace']),
                'enabled' => (bool) ($validated['enabled'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_system' => false,
            ]);

            $pages = is_array($validated['pages'] ?? null)
                ? array_values(array_filter($validated['pages'], is_array(...)))
                : [];

            $this->pages->handle($app, $pages);

            $app->load('pages');

            activity('apps')
                ->performedOn($app)
                ->causedBy($actor)
                ->event('created')
                ->withProperties([
                    'attributes' => AppData::fromModel($app)->toArray(),
                ])
                ->log('App registered');

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
