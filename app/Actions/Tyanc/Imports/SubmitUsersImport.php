<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Imports;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ExecuteApprovalControlledAction;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Data\Tyanc\Imports\ImportRunData;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

final readonly class SubmitUsersImport
{
    public function __construct(
        private ExecuteApprovalControlledAction $governedActions,
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

        $fileName = $file->getClientOriginalName();
        $subjectLabel = $fileName !== '' ? $fileName : (string) __('Users import');

        $submission = $this->governedActions->handle(
            actor: $actor,
            permissionName: $permission,
            context: [
                'file_name' => $subjectLabel,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'request_note' => $requestNote,
            ],
            definition: [
                'execute' => fn (): ImportRun => $this->imports->handle(
                    actor: $actor,
                    file: $file,
                    originalName: $subjectLabel,
                ),
                'proposal' => [
                    'request_note' => $this->nullableString($requestNote),
                    'payload' => [
                        'action_label' => __('Users import'),
                        'subject_label' => $subjectLabel,
                    ],
                    'subject_snapshot' => [
                        'file_name' => $subjectLabel,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ],
                ],
            ],
        );

        $importRun = $submission['result'] instanceof ImportRun
            ? $submission['result']
            : null;

        $approvalRequest = $submission['approval'] instanceof ApprovalRequest
            ? $submission['approval']
            : null;

        return [
            'executed' => (bool) $submission['executed'],
            'import' => $importRun instanceof ImportRun ? ImportRunData::fromModel($importRun) : null,
            'approval' => $approvalRequest instanceof ApprovalRequest
                ? ApprovalRequestData::fromModel($approvalRequest, $actor)
                : null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
