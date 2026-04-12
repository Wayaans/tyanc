<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Tyanc\Approvals\ExpireApprovalGrants;
use Carbon\CarbonInterface;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    use HasUuids;

    public const string StatusPending = 'pending';

    public const string StatusInReview = 'in_review';

    public const string StatusApproved = 'approved';

    public const string StatusRejected = 'rejected';

    public const string StatusCancelled = 'cancelled';

    public const string StatusExpired = 'expired';

    public const string StatusConsumed = 'consumed';

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
        'consumed_by_id',
        'request_note',
        'review_note',
        'payload',
        'subject_snapshot',
        'requested_at',
        'reviewed_at',
        'cancelled_at',
        'expires_at',
        'consumed_at',
        'superseded_at',
        'last_reassigned_at',
        'last_reminded_at',
        'escalated_at',
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
    public static function reviewableStatuses(): array
    {
        return [
            self::StatusPending,
            self::StatusInReview,
        ];
    }

    /**
     * @return list<string>
     */
    public static function blockingStatuses(): array
    {
        return [
            ...self::reviewableStatuses(),
            self::StatusApproved,
        ];
    }

    /**
     * @return list<string>
     */
    public static function consumableStatuses(): array
    {
        return [
            self::StatusApproved,
        ];
    }

    /**
     * @return list<string>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::StatusRejected,
            self::StatusCancelled,
            self::StatusExpired,
            self::StatusConsumed,
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeStatuses(): array
    {
        return self::reviewableStatuses();
    }

    public static function expirePastDueGrants(?CarbonInterface $referenceTime = null): int
    {
        return resolve(ExpireApprovalGrants::class)->handle($referenceTime);
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

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rule_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ApprovalAssignment::class, 'approval_request_id');
    }

    public function effectiveStatus(?CarbonInterface $referenceTime = null): string
    {
        return $this->grantHasExpired($referenceTime)
            ? self::StatusExpired
            : (string) $this->status;
    }

    public function grantHasExpired(?CarbonInterface $referenceTime = null): bool
    {
        if ((string) $this->status !== self::StatusApproved) {
            return false;
        }

        return $this->expires_at instanceof CarbonInterface
            && $this->expires_at->lte($referenceTime?->copy() ?? now());
    }

    public function isGrantConsumable(?CarbonInterface $referenceTime = null): bool
    {
        return in_array((string) $this->status, self::consumableStatuses(), true)
            && ! $this->grantHasExpired($referenceTime)
            && $this->consumed_at === null;
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
            'consumed_by_id' => 'string',
            'request_note' => 'string',
            'review_note' => 'string',
            'payload' => 'array',
            'subject_snapshot' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'last_reassigned_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'escalated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
