<?php

declare(strict_types=1);

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function messagingPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function messagingUser(array $permissions = []): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(messagingPermission(...), $permissions));

    return $user;
}

function messagingConversation(User ...$participants): Conversation
{
    $conversation = Conversation::factory()
        ->for($participants[0], 'creator')
        ->create();

    $conversation->participants()->attach(
        collect($participants)->mapWithKeys(fn (User $participant): array => [
            (string) $participant->id => [
                'last_read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ])->all(),
    );

    return $conversation;
}

it('renders the messages workspace and marks the selected conversation as read', function (): void {
    $actor = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'create'),
    ]);
    $otherParticipant = User::factory()->create();
    $conversation = messagingConversation($actor, $otherParticipant);

    Message::factory()
        ->for($conversation)
        ->for($otherParticipant, 'sender')
        ->create([
            'body' => 'Can you review the governance rollout?',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

    $conversation->forceFill([
        'last_message_at' => now()->subMinute(),
    ])->save();

    $this->actingAs($actor)
        ->get(route('tyanc.messages.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/messages/Index')
            ->where('selectedConversationId', (string) $conversation->id)
            ->where('selectedConversation.id', (string) $conversation->id)
            ->where('selectedConversation.messages.0.body', 'Can you review the governance rollout?')
            ->where('conversations.0.unread_count', 0)
            ->where('abilities.createConversation', true)
            ->where('contacts.0.id', (string) $otherParticipant->id));
});

it('shows contacts to message viewers but flags when they cannot create a conversation', function (): void {
    $viewer = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
    ]);
    $otherParticipant = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('tyanc.messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/messages/Index')
            ->where('abilities.createConversation', false)
            ->where('contacts.0.id', (string) $otherParticipant->id));
});

it('starts a new conversation and posts the first message', function (): void {
    $sender = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'create'),
    ]);
    $recipient = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('tyanc.messages.create'), [
            'participant_ids' => [(string) $recipient->id],
            'subject' => 'Phase 8 follow-up',
            'message' => 'Can we review the new message dropdown together?',
        ])
        ->assertCreated()
        ->assertJsonPath('conversation.subject', 'Phase 8 follow-up')
        ->assertJsonPath('selectedConversation.subject', 'Phase 8 follow-up')
        ->assertJsonPath('selectedConversation.messages.0.body', 'Can we review the new message dropdown together?');

    $conversation = Conversation::query()->where('subject', 'Phase 8 follow-up')->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation?->participants()->count())->toBe(2)
        ->and(Message::query()->where('conversation_id', $conversation?->id)->count())->toBe(1);
});

it('sends a message, dispatches the realtime event, and notifies the other participants', function (): void {
    $sender = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'create'),
    ]);
    $recipient = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
    ]);
    $conversation = messagingConversation($sender, $recipient);

    Notification::fake();
    Event::fake([MessageSent::class]);

    $this->actingAs($sender)
        ->postJson(route('tyanc.messages.store', $conversation), [
            'body' => 'I have finished the API scaffolding.',
        ])
        ->assertCreated()
        ->assertJsonPath('message.body', 'I have finished the API scaffolding.')
        ->assertJsonPath('selectedConversation.id', (string) $conversation->id)
        ->assertJsonPath('selectedConversation.messages.0.body', 'I have finished the API scaffolding.');

    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(1)
        ->and($conversation->fresh()?->last_message_at)->not->toBeNull();

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->conversation->is($conversation)
        && $event->message->body === 'I have finished the API scaffolding.');

    Notification::assertSentTo($recipient, NewMessageNotification::class);
});

it('reuses an existing direct conversation instead of creating a duplicate pair thread', function (): void {
    $sender = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'create'),
    ]);
    $recipient = User::factory()->create();
    $conversation = messagingConversation($sender, $recipient);

    Message::factory()
        ->for($conversation)
        ->for($sender, 'sender')
        ->create([
            'body' => 'Existing direct conversation message.',
        ]);

    $this->actingAs($sender)
        ->postJson(route('tyanc.messages.create'), [
            'participant_ids' => [(string) $recipient->id],
            'subject' => 'A duplicate-breaking subject',
            'message' => 'Follow-up in the same direct thread.',
        ])
        ->assertCreated()
        ->assertJsonPath('conversation.id', (string) $conversation->id)
        ->assertJsonPath('selectedConversation.id', (string) $conversation->id)
        ->assertJsonPath('selectedConversation.messages.1.body', 'Follow-up in the same direct thread.');

    expect(Conversation::query()->count())->toBe(1)
        ->and(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(2);
});

it('broadcasts messages immediately and rescues websocket broadcast failures', function (): void {
    $sender = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'create'),
    ]);
    $recipient = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
    ]);
    $conversation = messagingConversation($sender, $recipient);

    Notification::fake();

    $this->actingAs($sender)
        ->postJson(route('tyanc.messages.store', $conversation), [
            'body' => 'Broadcast this realtime update.',
        ])
        ->assertCreated()
        ->assertJsonPath('message.body', 'Broadcast this realtime update.');

    $message = Message::query()->where('conversation_id', $conversation->id)->latest('created_at')->first();

    expect($message)->toBeInstanceOf(Message::class);

    throw_unless($message instanceof Message, RuntimeException::class, 'Expected a stored message instance.');

    $event = new MessageSent($conversation, $message);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class);

    Notification::assertSentTo($recipient, NewMessageNotification::class);
});

it('rate limits rapid message sends to protect conversations from spam', function (): void {
    $sender = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'create'),
    ]);
    $recipient = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
    ]);
    $conversation = messagingConversation($sender, $recipient);

    Notification::fake();
    Queue::fake();

    foreach (range(1, 20) as $attempt) {
        $this->actingAs($sender)
            ->postJson(route('tyanc.messages.store', $conversation), [
                'body' => 'Message '.$attempt,
            ])
            ->assertCreated();
    }

    $this->actingAs($sender)
        ->postJson(route('tyanc.messages.store', $conversation), [
            'body' => 'Message 21',
        ])
        ->assertStatus(429);
});

it('archives conversations per participant and exposes them in the archived view', function (): void {
    $actor = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'archive'),
    ]);
    $otherParticipant = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
    ]);
    $conversation = messagingConversation($actor, $otherParticipant);

    Message::factory()
        ->for($conversation)
        ->for($otherParticipant, 'sender')
        ->create([
            'body' => "1. First line\n2. Second line",
        ]);

    $this->actingAs($actor)
        ->patchJson(route('tyanc.messages.archive', $conversation), [
            'archived' => true,
        ])
        ->assertOk()
        ->assertJsonPath('archivedConversationCount', 1)
        ->assertJsonPath('conversations', []);

    $this->actingAs($actor)
        ->get(route('tyanc.messages.index', ['view' => 'archived']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/messages/Index')
            ->where('viewMode', 'archived')
            ->where('archivedConversationCount', 1)
            ->where('conversations.0.id', (string) $conversation->id)
            ->where('conversations.0.last_message_preview', '1. First line 2. Second line')
            ->where('abilities.archiveConversation', true)
            ->where('abilities.deleteConversation', false));

    expect($conversation->participants()->whereKey($actor->getKey())->first()?->pivot?->archived_at)->not->toBeNull()
        ->and($conversation->participants()->whereKey($otherParticipant->getKey())->first()?->pivot?->archived_at)->toBeNull();
});

it('deletes archived conversations only for the acting participant when permitted', function (): void {
    $actor = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('messages', 'delete'),
    ]);
    $otherParticipant = messagingUser([
        PermissionKey::tyanc('messages', 'viewany'),
    ]);
    $conversation = messagingConversation($actor, $otherParticipant);

    $conversation->participants()->updateExistingPivot($actor->getKey(), [
        'archived_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($actor)
        ->deleteJson(route('tyanc.messages.destroy', ['conversation' => $conversation->id, 'view' => 'archived']))
        ->assertOk()
        ->assertJsonPath('archivedConversationCount', 0)
        ->assertJsonPath('conversations', []);

    expect($conversation->participants()->whereKey($actor->getKey())->exists())->toBeFalse()
        ->and($conversation->participants()->whereKey($otherParticipant->getKey())->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($conversation->id)->exists())->toBeTrue();
});

it('authorizes private conversation channels for participants', function (): void {
    $participant = messagingUser();
    $otherParticipant = messagingUser();
    $conversation = messagingConversation($participant, $otherParticipant);

    $this->actingAs($participant)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tyanc.conversations.'.$conversation->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk();
});

it('forbids the messages workspace without the correct permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tyanc.messages.index'))
        ->assertForbidden();
});
