<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Apps;

use App\Actions\Tyanc\Bootstrap\ResolveBootstrapStatus;
use App\Data\Tyanc\Apps\AppData;
use App\Exceptions\TyancBootstrapIncomplete;
use App\Models\App;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class ListApps
{
    public function __construct(private ResolveBootstrapStatus $bootstrapStatus) {}

    /**
     * @return array<int, AppData>
     */
    public function handle(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAny', App::class);

        $status = $this->bootstrapStatus->handle();
        $registryIssues = $this->bootstrapStatus->registryIssues($status);

        if ($registryIssues !== []) {
            throw TyancBootstrapIncomplete::forMissing(
                $registryIssues,
                $status['warnings'],
                'Tyanc bootstrap is incomplete. Run "php artisan tyanc:bootstrap --no-interaction" before opening the app registry.',
            );
        }

        return App::query()
            ->with('pages')
            ->ordered()
            ->get()
            ->map(fn (App $app): AppData => AppData::fromModel($app))
            ->all();
    }
}
