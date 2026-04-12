<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApprovalRuleStepFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $approval_rule_id
 * @property int $role_id
 * @property int $step_order
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ApprovalRule $rule
 * @property-read Role $role
 * @property-read Collection<int, ApprovalAssignment> $assignments
 */
final class ApprovalRuleStep extends Model
{
    /** @use HasFactory<ApprovalRuleStepFactory> */
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
        'approval_rule_id',
        'role_id',
        'step_order',
        'label',
    ];

    /**
     * @return BelongsTo<ApprovalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<ApprovalAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ApprovalAssignment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'approval_rule_id' => 'string',
            'role_id' => 'integer',
            'step_order' => 'integer',
            'label' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
