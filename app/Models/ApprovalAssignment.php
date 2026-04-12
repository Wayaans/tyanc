<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApprovalAssignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $approval_request_id
 * @property string|null $approval_rule_step_id
 * @property int|null $step_order_snapshot
 * @property string|null $step_label_snapshot
 * @property string|null $role_name_snapshot
 * @property string|null $assigned_to_id
 * @property string $status
 * @property string|null $completed_by_id
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ApprovalRequest $request
 * @property-read ApprovalRuleStep|null $step
 * @property-read User|null $assignee
 * @property-read User|null $completedBy
 */
final class ApprovalAssignment extends Model
{
    /** @use HasFactory<ApprovalAssignmentFactory> */
    use HasFactory;

    use HasUuids;

    public const string StatusPending = 'pending';

    public const string StatusCompleted = 'completed';

    public const string StatusCancelled = 'cancelled';

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
        'approval_request_id',
        'approval_rule_step_id',
        'step_order_snapshot',
        'step_label_snapshot',
        'role_name_snapshot',
        'assigned_to_id',
        'status',
        'completed_by_id',
        'completed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::StatusPending,
    ];

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /**
     * @return BelongsTo<ApprovalRuleStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalRuleStep::class, 'approval_rule_step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'approval_request_id' => 'string',
            'approval_rule_step_id' => 'string',
            'step_order_snapshot' => 'integer',
            'step_label_snapshot' => 'string',
            'role_name_snapshot' => 'string',
            'assigned_to_id' => 'string',
            'status' => 'string',
            'completed_by_id' => 'string',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
