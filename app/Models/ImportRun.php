<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class ImportRun extends Model implements HasMedia
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMedia;

    public const string SourceFileCollection = 'source_file';

    public const string TypeUsers = 'users';

    public const string StatusPendingApproval = 'pending_approval';

    public const string StatusQueued = 'queued';

    public const string StatusProcessing = 'processing';

    public const string StatusCompleted = 'completed';

    public const string StatusFailed = 'failed';

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
        'type',
        'status',
        'file_name',
        'processed_rows',
        'meta',
        'failure_message',
        'created_by_id',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::StatusPendingApproval,
        'processed_rows' => 0,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'subject');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SourceFileCollection)->singleFile();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'type' => 'string',
            'status' => 'string',
            'file_name' => 'string',
            'processed_rows' => 'integer',
            'meta' => 'array',
            'failure_message' => 'string',
            'created_by_id' => 'string',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
