<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class App extends Model
{
    /** @use HasFactory<AppFactory> */
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
        'key',
        'label',
        'route_prefix',
        'icon',
        'permission_namespace',
        'enabled',
        'sort_order',
        'is_system',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => true,
        'sort_order' => 0,
        'is_system' => false,
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function pages(): HasMany
    {
        return $this->hasMany(AppPage::class)
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    protected function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    protected function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'key' => 'string',
            'label' => 'string',
            'route_prefix' => 'string',
            'icon' => 'string',
            'permission_namespace' => 'string',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
