<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Tyanc\Imports\QueueUsersImport;
use App\Contracts\Approvals\DeferredApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use RuntimeException;

final readonly class ApplyUsersImportApproval implements DeferredApprovalAction
{
    public function __construct(private QueueUsersImport $imports) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(ApprovalRequest $approvalRequest, array $payload = []): ImportRun
    {
        $approvalRequest->loadMissing('requester');

        $requester = $approvalRequest->requester;

        if (! $requester instanceof User) {
            throw new RuntimeException(__('The approval requester could not be resolved.'));
        }

        $stagedFilePath = is_string($payload['staged_file_path'] ?? null)
            ? $payload['staged_file_path']
            : null;

        if ($stagedFilePath === null || $stagedFilePath === '') {
            throw new RuntimeException(__('The staged import file is missing.'));
        }

        $importRun = $this->imports->handle(
            actor: $requester,
            file: $stagedFilePath,
            originalName: is_string($payload['original_name'] ?? null)
                ? $payload['original_name']
                : null,
        );

        $approvalRequest->forceFill([
            'subject_type' => $importRun::class,
            'subject_id' => $importRun->id,
        ])->save();

        return $importRun;
    }
}
