<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject',
        'created_by_id',
        'last_message_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['last_read_at', 'archived_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function hasParticipant(User $user): bool
    {
        $this->loadMissing('participants');

        return $this->participants->contains(fn (User $participant): bool => $participant->is($user));
    }

    public function titleFor(?User $viewer = null): string
    {
        $this->loadMissing('participants.profile');

        $participants = $this->participants;

        if ($viewer instanceof User) {
            $participants = $participants
                ->reject(fn (User $participant): bool => $participant->is($viewer))
                ->values();
        }

        $names = $participants
            ->map(fn (User $participant): string => $participant->name)
            ->filter(fn (string $name): bool => $name !== '')
            ->values();

        if ($names->isNotEmpty()) {
            if ($names->count() <= 2) {
                return $names->implode(', ');
            }

            return sprintf('%s +%d', $names->take(2)->implode(', '), $names->count() - 2);
        }

        if (is_string($this->subject) && $this->subject !== '') {
            return $this->subject;
        }

        return __('Conversation');
    }

    protected function scopeForParticipant(Builder $query, User $user): Builder
    {
        return $query->forParticipantState($user, false);
    }

    protected function scopeForParticipantState(Builder $query, User $user, bool $archived): Builder
    {
        return $query->whereHas('participants', function (Builder $participants) use ($user, $archived): Builder {
            $participants->whereKey($user->getKey());

            return $archived
                ? $participants->whereNotNull('conversation_user.archived_at')
                : $participants->whereNull('conversation_user.archived_at');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'subject' => 'string',
            'created_by_id' => 'string',
            'last_message_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
