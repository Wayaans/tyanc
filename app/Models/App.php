<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvals\ApprovalSubject;
use App\Models\Concerns\InteractsWithApprovals;
use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class App extends Model implements ApprovalSubject
{
    /** @use HasFactory<AppFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithApprovals;

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

    /**
     * @return HasMany<AppPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(AppPage::class)
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    public function approvalAppKey(): string
    {
        return 'tyanc';
    }

    public function approvalResourceKey(): string
    {
        return 'apps';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalSubjectSnapshot(): array
    {
        $this->loadMissing('pages');

        return [
            'id' => (string) $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'route_prefix' => $this->route_prefix,
            'permission_namespace' => $this->permission_namespace,
            'enabled' => $this->enabled,
            'pages' => $this->pages
                ->map(fn (AppPage $page): array => [
                    'key' => $page->key,
                    'label' => $page->label,
                    'route_name' => $page->route_name,
                    'path' => $page->path,
                    'permission_name' => $page->permission_name,
                    'sort_order' => $page->sort_order,
                    'enabled' => $page->enabled,
                    'is_navigation' => $page->is_navigation,
                    'is_system' => $page->is_system,
                ])
                ->sortBy('key')
                ->values()
                ->all(),
        ];
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
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
