<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SendMessage
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Conversation $conversation, array $attributes): Message
    {
        Gate::forUser($actor)->authorize(PermissionKey::tyanc('messages', 'create'));

        throw_if(! $conversation->participants()->whereKey($actor->getKey())->exists(), AuthorizationException::class);

        $message = DB::transaction(function () use ($actor, $conversation, $attributes): Message {
            $conversation->loadMissing('participants');

            $message = $conversation->messages()->create([
                'sender_id' => $actor->id,
                'body' => mb_trim((string) ($attributes['body'] ?? '')),
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
            ])->save();

            $conversation->participants()->updateExistingPivot($actor->getKey(), [
                'last_read_at' => $message->created_at,
                'archived_at' => null,
                'updated_at' => now(),
            ]);

            foreach ($conversation->participants as $participant) {
                if ($participant->isNot($actor)) {
                    $conversation->participants()->updateExistingPivot($participant->getKey(), [
                        'archived_at' => null,
                        'updated_at' => now(),
                    ]);
                }

                if ($participant->is($actor)) {
                    continue;
                }

                $participant->notify(new NewMessageNotification($conversation, $message, $actor));
            }

            activity('messaging')
                ->performedOn($conversation)
                ->causedBy($actor)
                ->event('sent')
                ->withProperties([
                    'conversation_id' => (string) $conversation->id,
                    'message_id' => (string) $message->id,
                ])
                ->log('Message sent');

            return $message;
        });

        $message->loadMissing(['sender', 'conversation.participants', 'conversation.latestMessage.sender']);

        event(new MessageSent($message->conversation, $message));

        return $message;
    }
}
