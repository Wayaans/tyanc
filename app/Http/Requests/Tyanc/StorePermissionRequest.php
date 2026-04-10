<?php

declare(strict_types=1);

namespace App\Http\Requests\Tyanc;

use App\Models\App;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_key' => ['required', 'string', Rule::exists(App::class, 'key')],
            'resource_key' => ['required', 'string', Rule::in($this->allowedResources())],
            'action_key' => ['required', 'string', Rule::in($this->allowedActions())],
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$/', Rule::unique(Permission::class, 'name')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $appKey = $this->string('app_key')->toString();
        $resourceKey = $this->string('resource_key')->toString();
        $actionKey = $this->string('action_key')->toString();

        $name = collect([$appKey, $resourceKey, $actionKey])
            ->map(fn (string $segment): string => mb_strtolower(mb_trim($segment)))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->implode('.');

        $this->merge([
            'name' => $name,
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedResources(): array
    {
        $appKey = $this->string('app_key')->toString();
        $resources = config(sprintf('permission-resources.apps.%s', $appKey), []);

        if (! is_array($resources)) {
            return [];
        }

        return array_values(array_keys($resources));
    }

    /**
     * @return list<string>
     */
    private function allowedActions(): array
    {
        $actions = config('permission-resources.actions', []);

        return is_array($actions)
            ? array_values(array_keys($actions))
            : [];
    }
}
