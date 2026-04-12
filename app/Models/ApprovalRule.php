<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApprovalRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * @var list<string>
     */
    protected $fillable = [
        'app_key',
        'resource_key',
        'action_key',
        'permission_name',
        'enabled',
        'workflow_type',
        'conditions',
        'reminder_after_minutes',
        'escalation_after_minutes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => false,
        'workflow_type' => self::WorkflowSingle,
    ];

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
            'workflow_type' => 'string',
            'conditions' => 'array',
            'reminder_after_minutes' => 'integer',
            'escalation_after_minutes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
