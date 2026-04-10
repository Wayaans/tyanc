<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AppPageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AppPage extends Model
{
    /** @use HasFactory<AppPageFactory> */
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
        'app_id',
        'key',
        'label',
        'route_name',
        'path',
        'permission_name',
        'sort_order',
        'enabled',
        'is_navigation',
        'is_system',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => true,
        'sort_order' => 0,
        'is_navigation' => true,
        'is_system' => false,
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'app_id' => 'string',
            'key' => 'string',
            'label' => 'string',
            'route_name' => 'string',
            'path' => 'string',
            'permission_name' => 'string',
            'sort_order' => 'integer',
            'enabled' => 'boolean',
            'is_navigation' => 'boolean',
            'is_system' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
