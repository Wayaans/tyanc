<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use Spatie\Activitylog\Models\Activity;

function activityManager(): User
{
    $user = User::factory()->create();

    $permission = Permission::query()->firstOrCreate([
        'name' => 'manage-users',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    return $user;
}

it('logs user update, suspension, deletion, and login activity events', function (): void {
    $manager = activityManager();
    $managedUser = User::factory()->create([
        'password' => 'password',
    ]);

    Activity::query()->delete();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.users.update', $managedUser), [
            'username' => 'updated-user',
            'email' => 'updated@example.com',
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'UTC',
        ])
        ->assertOk();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.users.suspend', $managedUser))
        ->assertOk();

    $this->actingAs($manager)
        ->deleteJson(route('tyanc.users.destroy', $managedUser))
        ->assertNoContent();

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $manager->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect(Activity::query()->where('description', 'User updated')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'User suspended')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'User deleted')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'login')->count())->toBe(1);
});

it('shares unread notifications and marks them as read', function (): void {
    $manager = activityManager();

    $manager->notify(new NewApprovalRequestedNotification());
    $manager->notify(new NewApprovalRequestedNotification());

    $firstNotification = $manager->notifications()->latest()->first();
    expect($firstNotification)->not->toBeNull();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/Dashboard')
            ->where('notifications.unread_count', 2)
            ->where('notifications.recent.0.title', 'New approval requested'));

    $this->actingAs($manager)
        ->patchJson(route('tyanc.notifications.update', $firstNotification))
        ->assertOk()
        ->assertJsonPath('notification.read', true);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.notifications.mark-all-read'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});

it('renders and filters the activity log page for authorized managers', function (): void {
    $manager = activityManager();
    $subject = User::factory()->create();

    Activity::query()->delete();

    activity('users')
        ->performedOn($subject)
        ->causedBy($manager)
        ->event('created')
        ->log('User created');

    activity('auth')
        ->performedOn($manager)
        ->causedBy($manager)
        ->event('login')
        ->log('User signed in');

    $this->actingAs($manager)
        ->get(route('tyanc.activity-log.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/activity-log/Index')
            ->where('activitiesTable.meta.total', 2));

    $this->actingAs($manager)
        ->getJson(route('tyanc.activity-log.index', [
            'filter' => [
                'event' => 'login',
            ],
            'sort' => ['-created_at'],
        ]))
        ->assertOk()
        ->assertJsonPath('activitiesTable.meta.total', 1)
        ->assertJsonPath('activitiesTable.rows.0.event', 'login');
});

it('shows login activity for Supa Manuse users through the activity log index', function (): void {
    $superRole = Role::query()->create([
        'name' => config('tyanc.reserved_roles.super_admin'),
        'guard_name' => 'web',
        'level' => 100,
    ]);

    $superUser = User::factory()->create([
        'password' => 'password',
    ]);
    $superUser->assignRole($superRole);

    Activity::query()->delete();

    $this->post(route('login.store'), [
        'email' => $superUser->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('tyanc.activity-log.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/activity-log/Index')
            ->where('activitiesTable.meta.total', 1)
            ->where('activitiesTable.rows.0.event', 'login')
            ->where('activitiesTable.rows.0.description', 'User signed in'));
});

it('forbids the activity log without the manage-users permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tyanc.activity-log.index'))
        ->assertForbidden();
});
