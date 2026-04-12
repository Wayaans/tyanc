<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvals\ApprovalSubject;
use App\Models\Concerns\InteractsWithApprovals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $level
 * @property string $name
 * @property string $guard_name
 */
final class Role extends SpatieRole implements ApprovalSubject
{
    use HasFactory;
    use InteractsWithApprovals;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'level',
    ];

    public function approvalAppKey(): string
    {
        return 'tyanc';
    }

    public function approvalResourceKey(): string
    {
        return 'roles';
    }

    public function isHigherThan(self $role): bool
    {
        return $this->level > $role->level;
    }

    public function isHigherThanOrEqualTo(self $role): bool
    {
        return $this->level >= $role->level;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'int',
            'name' => 'string',
            'guard_name' => 'string',
            'level' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
