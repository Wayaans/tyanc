<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    use HasUuids;

    public const string StatusPending = 'pending';

    public const string StatusApproved = 'approved';

    public const string StatusRejected = 'rejected';

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
        'action',
        'status',
        'subject_type',
        'subject_id',
        'requested_by_id',
        'reviewed_by_id',
        'request_note',
        'review_note',
        'payload',
        'requested_at',
        'reviewed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::StatusPending,
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'action' => 'string',
            'status' => 'string',
            'subject_type' => 'string',
            'subject_id' => 'string',
            'requested_by_id' => 'string',
            'reviewed_by_id' => 'string',
            'request_note' => 'string',
            'review_note' => 'string',
            'payload' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
