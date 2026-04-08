<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

final class Role extends SpatieRole
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'level',
    ];

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
