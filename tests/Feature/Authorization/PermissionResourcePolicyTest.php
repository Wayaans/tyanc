<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Policies\UserPolicy;

it('authorizes viewAny from the configured resource action', function (): void {
    Permission::query()->firstOrCreate([
        'name' => 'tyanc.users.viewany',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('tyanc.users.viewany');

    expect(new UserPolicy()->viewAny($viewer))->toBeTrue();
});

it('falls back to manage for abilities included in the configured manage mapping', function (): void {
    Permission::query()->firstOrCreate([
        'name' => 'tyanc.users.manage',
        'guard_name' => 'web',
    ]);

    $manager = User::factory()->create();
    $manager->givePermissionTo('tyanc.users.manage');

    expect(new UserPolicy()->viewAny($manager))->toBeTrue()
        ->and(new UserPolicy()->create($manager))->toBeTrue();
});
