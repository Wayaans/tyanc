<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootAuthorizationRules();
        $this->bootReservedRoleRules();
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

    private function bootReservedRoleRules(): void
    {
        $reservedRoles = array_values(config('tyanc.reserved_roles', []));

        Role::updating(function (Role $role) use ($reservedRoles): void {
            $originalName = $role->getOriginal('name');

            throw_if(is_string($originalName) && in_array($originalName, $reservedRoles, true) && $role->name !== $originalName, LogicException::class, 'Reserved roles cannot be renamed.');
        });

        Role::deleting(function (Role $role) use ($reservedRoles): void {
            throw_if(in_array($role->name, $reservedRoles, true), LogicException::class, 'Reserved roles cannot be deleted.');
        });
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
        });
    }
}
