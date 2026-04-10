<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\App;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Observers\AppObserver;
use App\Observers\PermissionObserver;
use App\Observers\RoleObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootAuthorizationRules();
        $this->bootObservers();
        $this->bootLoginTelemetry();
    }

    private function bootAuthorizationRules(): void
    {
        Gate::before(function (mixed $user): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            return $user->hasRole(config('tyanc.reserved_roles.super_admin')) ? true : null;
        });
    }

    private function bootObservers(): void
    {
        App::observe(AppObserver::class);
        Permission::observe(PermissionObserver::class);
        Role::observe(RoleObserver::class);
        User::observe(UserObserver::class);
    }

    private function bootLoginTelemetry(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->saveQuietly();

            activity('auth')
                ->performedOn($event->user)
                ->causedBy($event->user)
                ->event('login')
                ->withProperties([
                    'ip_address' => request()->ip(),
                ])
                ->log('User signed in');
        });
    }
}
