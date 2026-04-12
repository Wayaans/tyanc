<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Imports;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ApplyUsersImportApproval;
use App\Actions\Tyanc\Approvals\CreateApprovalProposal;
use App\Actions\Tyanc\Approvals\ResolveApprovalRule;
use App\Actions\Tyanc\Approvals\ShouldBypassApproval;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Data\Tyanc\Imports\ImportRunData;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class SubmitUsersImport
{
    public function __construct(
        private ResolveApprovalRule $rules,
        private ShouldBypassApproval $bypassApproval,
        private CreateApprovalProposal $approvalRequests,
        private QueueUsersImport $imports,
    ) {}

    /**
     * @return array{executed: bool, import: ImportRunData|null, approval: ApprovalRequestData|null}
     */
    public function handle(User $actor, UploadedFile $file, ?string $requestNote = null): array
    {
        $permission = PermissionKey::tyanc('users', 'import');

        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, $permission),
            AuthorizationException::class,
        );

        $rule = $this->rules->handle($actor, $permission, context: [
            'file_name' => $file->getClientOriginalName(),
        ]);

        if (! $rule instanceof ApprovalRule || $this->bypassApproval->handle($actor, $rule)) {
            $importRun = $this->imports->handle($actor, $file);

            return [
                'executed' => true,
                'import' => ImportRunData::fromModel($importRun),
                'approval' => null,
            ];
        }

        $stagedPath = $this->stageFile($file);

        try {
            $approvalRequest = $this->approvalRequests->handle(
                actor: $actor,
                rule: $rule,
                permissionName: $permission,
                subject: null,
                attributes: [
                    'request_note' => $requestNote,
                    'impact_summary' => __('Import file :file for user processing.', [
                        'file' => $file->getClientOriginalName(),
                    ]),
                    'payload' => [
                        'action_label' => __('Users import'),
                        'subject_label' => $file->getClientOriginalName(),
                        'action_url' => route('cumpu.approvals.my-requests', absolute: false),
                    ],
                    'after_payload' => [
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ],
                    'handler' => ApplyUsersImportApproval::class,
                    'action_payload' => [
                        'staged_file_path' => $stagedPath,
                        'original_name' => $file->getClientOriginalName(),
                    ],
                ],
            );
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($stagedPath);

            throw $throwable;
        }

        return [
            'executed' => false,
            'import' => null,
            'approval' => ApprovalRequestData::fromModel($approvalRequest, $actor),
        ];
    }

    private function stageFile(UploadedFile $file): string
    {
        $safeFileName = Str::of($file->getClientOriginalName())
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->value();

        return Storage::disk('local')->putFileAs(
            'approvals/imports',
            $file,
            Str::uuid()->toString().'_'.$safeFileName,
        );
    }
}
