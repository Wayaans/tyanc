<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvals\ApprovalSubject;
use App\Models\Concerns\InteractsWithApprovals;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $type
 * @property string $status
 * @property string|null $file_name
 * @property int $processed_rows
 * @property array<string, mixed>|null $meta
 * @property string|null $failure_message
 * @property string|null $created_by_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, ApprovalRequest> $approvalRequests
 */
#[Fillable([
    'type',
    'status',
    'file_name',
    'processed_rows',
    'meta',
    'failure_message',
    'created_by_id',
    'started_at',
    'finished_at',
])]
final class ImportRun extends Model implements ApprovalSubject, HasMedia
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithApprovals;
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
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::StatusPendingApproval,
        'processed_rows' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvalAppKey(): string
    {
        return 'tyanc';
    }

    public function approvalResourceKey(): string
    {
        return 'users';
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
