<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    use HasUuids;

    public const string StatusDraft = 'draft';

    public const string StatusPending = 'pending';

    public const string StatusInReview = 'in_review';

    public const string StatusApproved = 'approved';

    public const string StatusRejected = 'rejected';

    public const string StatusCancelled = 'cancelled';

    public const string StatusExpired = 'expired';

    public const string StatusSuperseded = 'superseded';

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
        'rule_id',
        'action',
        'app_key',
        'resource_key',
        'action_key',
        'status',
        'subject_type',
        'subject_id',
        'requested_by_id',
        'reviewed_by_id',
        'cancelled_by_id',
        'previous_request_id',
        'superseded_by_id',
        'request_note',
        'review_note',
        'payload',
        'subject_snapshot',
        'before_payload',
        'after_payload',
        'impact_summary',
        'requested_at',
        'reviewed_at',
        'cancelled_at',
        'expires_at',
        'superseded_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::StatusPending,
    ];

    /**
     * @return list<string>
     */
    public static function activeStatuses(): array
    {
        return [
            self::StatusPending,
            self::StatusInReview,
        ];
    }

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

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rule_id');
    }

    public function actionRecord(): HasOne
    {
        return $this->hasOne(ApprovalAction::class, 'approval_request_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ApprovalAssignment::class, 'approval_request_id');
    }

    public function previousRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_request_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'rule_id' => 'string',
            'action' => 'string',
            'app_key' => 'string',
            'resource_key' => 'string',
            'action_key' => 'string',
            'status' => 'string',
            'subject_type' => 'string',
            'subject_id' => 'string',
            'requested_by_id' => 'string',
            'reviewed_by_id' => 'string',
            'cancelled_by_id' => 'string',
            'previous_request_id' => 'string',
            'superseded_by_id' => 'string',
            'request_note' => 'string',
            'review_note' => 'string',
            'payload' => 'array',
            'subject_snapshot' => 'array',
            'before_payload' => 'array',
            'after_payload' => 'array',
            'impact_summary' => 'string',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'superseded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
