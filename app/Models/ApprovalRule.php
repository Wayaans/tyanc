<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalMode;
use Database\Factories\ApprovalRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $app_key
 * @property string $resource_key
 * @property string $action_key
 * @property string $permission_name
 * @property bool $enabled
 * @property ApprovalMode $mode
 * @property bool $managed_by_config
 * @property string|null $source_key
 * @property string|null $config_hash
 * @property Carbon|null $retired_at
 * @property string|null $retired_reason
 * @property string $workflow_type
 * @property array<string, mixed>|null $conditions
 * @property int|null $grant_validity_minutes
 * @property int|null $reminder_after_minutes
 * @property int|null $escalation_after_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ApprovalRuleStep> $steps
 */
#[Fillable([
    'app_key',
    'resource_key',
    'action_key',
    'permission_name',
    'enabled',
    'mode',
    'managed_by_config',
    'source_key',
    'config_hash',
    'retired_at',
    'retired_reason',
    'workflow_type',
    'conditions',
    'grant_validity_minutes',
    'reminder_after_minutes',
    'escalation_after_minutes',
])]
final class ApprovalRule extends Model
{
    /** @use HasFactory<ApprovalRuleFactory> */
    use HasFactory;

    use HasUuids;

    public const string WorkflowSingle = 'single';

    public const string WorkflowMulti = 'multi';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => false,
        'mode' => ApprovalMode::Grant->value,
        'managed_by_config' => false,
        'workflow_type' => self::WorkflowSingle,
        'grant_validity_minutes' => 1440,
    ];

    /**
     * @return HasMany<ApprovalRuleStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRuleStep::class)
            ->orderBy('step_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'app_key' => 'string',
            'resource_key' => 'string',
            'action_key' => 'string',
            'permission_name' => 'string',
            'enabled' => 'boolean',
            'mode' => ApprovalMode::class,
            'managed_by_config' => 'boolean',
            'source_key' => 'string',
            'config_hash' => 'string',
            'retired_at' => 'datetime',
            'retired_reason' => 'string',
            'workflow_type' => 'string',
            'conditions' => 'array',
            'grant_validity_minutes' => 'integer',
            'reminder_after_minutes' => 'integer',
            'escalation_after_minutes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
