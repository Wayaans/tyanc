<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
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
        'conversation_id',
        'sender_id',
        'body',
    ];

    /**
     * @var list<string>
     */
    protected $touches = [
        'conversation',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'conversation_id' => 'string',
            'sender_id' => 'string',
            'body' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
