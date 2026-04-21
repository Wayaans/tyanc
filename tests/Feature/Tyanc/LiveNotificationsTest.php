<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use App\Notifications\UserStatusChangedNotification;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;

function liveNotificationUser(): User
{
    return User::factory()->create();
}

it('stores approval notifications for inbox reads and broadcasts live payloads to the authenticated user channel', function (): void {
    $this->seed(AppRegistrySeeder::class);

    Event::fake([BroadcastNotificationCreated::class]);

    $user = liveNotificationUser();

    $user->notify(new NewApprovalRequestedNotification());

    $this->actingAs($user)
        ->getJson(route('tyanc.notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('recent.0.kind', 'approval-request')
        ->assertJsonPath('recent.0.title', 'New approval requested')
        ->assertJsonPath('recent.0.read', false);

    Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($user): bool {
        $channels = $event->broadcastOn();
        $payload = $event->broadcastWith();

        return $event->notification instanceof NewApprovalRequestedNotification
            && count($channels) === 1
            && $channels[0] instanceof PrivateChannel
            && $channels[0]->name === sprintf('private-App.Models.User.%s', $user->id)
            && data_get($payload, 'kind') === 'approval-request'
            && data_get($payload, 'title') === 'New approval requested'
            && data_get($payload, 'read') === false
            && data_get($payload, 'read_at') === null
            && is_string(data_get($payload, 'created_at'))
            && is_string(data_get($payload, 'id'))
            && data_get($payload, 'type') === NewApprovalRequestedNotification::class;
    });
});

it('stores system notifications for inbox reads and broadcasts them live to the authenticated user channel', function (): void {
    $this->seed(AppRegistrySeeder::class);

    Event::fake([BroadcastNotificationCreated::class]);

    $user = liveNotificationUser();

    $user->notify(new UserStatusChangedNotification($user, 'pending', 'active'));

    $this->actingAs($user)
        ->getJson(route('tyanc.notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('recent.0.kind', 'user-status')
        ->assertJsonPath('recent.0.title', 'User status updated')
        ->assertJsonPath('recent.0.read', false);

    Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($user): bool {
        $channels = $event->broadcastOn();
        $payload = $event->broadcastWith();

        return $event->notification instanceof UserStatusChangedNotification
            && count($channels) === 1
            && $channels[0] instanceof PrivateChannel
            && $channels[0]->name === sprintf('private-App.Models.User.%s', $user->id)
            && data_get($payload, 'kind') === 'user-status'
            && data_get($payload, 'title') === 'User status updated'
            && is_string(data_get($payload, 'id'))
            && data_get($payload, 'type') === UserStatusChangedNotification::class;
    });
});
