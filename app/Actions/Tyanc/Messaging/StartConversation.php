<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Messaging;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class StartConversation
{
    public function __construct(private SendMessage $sendMessage) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes): Conversation
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('messages', 'create')),
            AuthorizationException::class,
        );

        $participantIds = $this->participantIds($actor, $attributes['participant_ids'] ?? []);
        $subject = $this->nullableString($attributes['subject'] ?? null);
        $messageBody = $this->nullableString($attributes['message'] ?? null);

        $persistConversation = fn (): Conversation => DB::transaction(function () use ($actor, $participantIds, $subject): Conversation {
            $existingConversation = $this->existingDirectConversation($actor, $participantIds);

            if ($existingConversation instanceof Conversation) {
                return $existingConversation;
            }

            $conversation = Conversation::query()->create([
                'subject' => $subject,
                'created_by_id' => $actor->id,
            ]);

            $conversation->participants()->attach([
                (string) $actor->id => [
                    'last_read_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ...$participantIds
                    ->mapWithKeys(fn (string $participantId): array => [
                        $participantId => [
                            'last_read_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ])
                    ->all(),
            ]);

            return $conversation->load('participants');
        });

        $conversation = $participantIds->count() === 1
            ? Cache::lock($this->directConversationLockKey($actor, (string) $participantIds->first()), 10)->block(5, $persistConversation)
            : $persistConversation();

        if ($messageBody !== null) {
            $this->sendMessage->handle($actor, $conversation, [
                'body' => $messageBody,
            ]);
        }

        return $conversation->fresh(['participants', 'latestMessage.sender']) ?? $conversation;
    }

    /**
     * @return Collection<int, string>
     */
    private function participantIds(User $actor, mixed $value): Collection
    {
        return collect((array) $value)
            ->filter(fn (mixed $participantId): bool => is_string($participantId) || is_numeric($participantId))
            ->map(fn (mixed $participantId): string => (string) $participantId)
            ->filter(fn (string $participantId): bool => $participantId !== '' && $participantId !== (string) $actor->id)
            ->unique()
            ->values();
    }

    private function existingDirectConversation(User $actor, Collection $participantIds): ?Conversation
    {
        if ($participantIds->count() !== 1) {
            return null;
        }

        $otherParticipantId = $participantIds->first();

        if (! is_string($otherParticipantId) || $otherParticipantId === '') {
            return null;
        }

        return Conversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($actor->getKey()))
            ->whereHas('participants', fn ($query) => $query->whereKey($otherParticipantId))
            ->has('participants', '=', 2)
            ->latest('updated_at')
            ->first();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private function directConversationLockKey(User $actor, string $otherParticipantId): string
    {
        $participantIds = [(string) $actor->id, $otherParticipantId];
        sort($participantIds);

        return sprintf('tyanc:messages:direct:%s', implode(':', $participantIds));
    }
}
