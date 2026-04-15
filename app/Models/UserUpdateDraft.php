<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvals\DraftApprovalSubject;
use App\Models\Concerns\InteractsWithApprovals;
use Database\Factories\UserUpdateDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $created_by_id
 * @property string|null $committed_by_id
 * @property int $revision
 * @property array<string, mixed>|null $payload
 * @property array<int, string>|null $changed_fields
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User $creator
 * @property-read User|null $committedBy
 */
#[Fillable([
    'user_id',
    'created_by_id',
    'committed_by_id',
    'revision',
    'payload',
    'changed_fields',
    'committed_at',
])]
final class UserUpdateDraft extends Model implements DraftApprovalSubject
{
    /** @use HasFactory<UserUpdateDraftFactory> */
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function committedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by_id');
    }

    public function approvalAppKey(): string
    {
        return 'tyanc';
    }

    public function approvalResourceKey(): string
    {
        return 'users';
    }

    public function approvalSubjectRevision(): string
    {
        return (string) $this->revision;
    }

    public function approvalSubjectLabel(): string
    {
        $this->loadMissing('user');

        return __('Update :name', [
            'name' => $this->user->approvalSubjectLabel(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalSubjectSnapshot(): array
    {
        $this->loadMissing('user');

        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'user_name' => $this->user->approvalSubjectLabel(),
            'revision' => (string) $this->revision,
            'changed_fields' => $this->changed_fields ?? [],
            'proposed' => $this->reviewSnapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForPersistence(): array
    {
        return is_array($this->payload) ? $this->payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function formPayload(): array
    {
        $payload = $this->attributesForPersistence();

        unset($payload['password']);

        return $payload;
    }

    public function hasPasswordChange(): bool
    {
        $password = $this->attributesForPersistence()['password'] ?? null;

        return is_string($password) && mb_trim($password) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewSnapshot(): array
    {
        $payload = $this->attributesForPersistence();

        return collect($payload)
            ->except(['password'])
            ->when(
                $this->hasPasswordChange(),
                fn ($collection) => $collection->put('password', __('Updated')),
            )
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'created_by_id' => 'string',
            'committed_by_id' => 'string',
            'revision' => 'integer',
            'payload' => 'encrypted:array',
            'changed_fields' => 'array',
            'committed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
