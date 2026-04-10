<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Imports;

use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use Spatie\LaravelData\Data;

final class ImportRunData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public string $file_name,
        public int $processed_rows,
        public ?string $failure_message,
        public ?string $created_by_id,
        public ?string $created_by_name,
        public ?string $approval_request_id,
        public ?string $approval_status,
        public ?string $approval_reviewed_at,
        public ?string $started_at,
        public ?string $finished_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ImportRun $importRun): self
    {
        $importRun->loadMissing('creator', 'approvalRequests');

        /** @var ApprovalRequest|null $approval */
        $approval = $importRun->approvalRequests
            ->sortByDesc('requested_at')
            ->first();

        return new self(
            id: (string) $importRun->id,
            type: (string) $importRun->type,
            status: (string) $importRun->status,
            file_name: (string) ($importRun->file_name ?? 'import.xlsx'),
            processed_rows: (int) $importRun->processed_rows,
            failure_message: $importRun->failure_message,
            created_by_id: $importRun->created_by_id,
            created_by_name: $importRun->creator instanceof User ? $importRun->creator->name : null,
            approval_request_id: $approval?->id,
            approval_status: $approval?->status,
            approval_reviewed_at: $approval?->reviewed_at?->toIso8601String(),
            started_at: $importRun->started_at?->toIso8601String(),
            finished_at: $importRun->finished_at?->toIso8601String(),
            created_at: $importRun->created_at?->toIso8601String() ?? now()->toIso8601String(),
            updated_at: $importRun->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }
}
