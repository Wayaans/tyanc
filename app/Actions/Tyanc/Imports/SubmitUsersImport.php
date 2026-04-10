<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Imports;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\SubmitApprovalRequest;
use App\Data\Tyanc\Approvals\ApprovalRequestData;
use App\Data\Tyanc\Imports\ImportRunData;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class SubmitUsersImport
{
    public function __construct(private SubmitApprovalRequest $approvalRequests) {}

    /**
     * @return array{import: ImportRunData, approval: ApprovalRequestData}
     */
    public function handle(User $actor, UploadedFile $file, ?string $requestNote = null): array
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('users', 'import')),
            AuthorizationException::class,
        );

        return DB::transaction(function () use ($actor, $file, $requestNote): array {
            $importRun = ImportRun::query()->create([
                'type' => ImportRun::TypeUsers,
                'status' => ImportRun::StatusPendingApproval,
                'file_name' => $file->getClientOriginalName(),
                'processed_rows' => 0,
                'created_by_id' => $actor->id,
            ]);

            $importRun
                ->addMedia($file)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection(ImportRun::SourceFileCollection);

            activity('imports')
                ->performedOn($importRun)
                ->causedBy($actor)
                ->event('submitted')
                ->withProperties([
                    'attributes' => ImportRunData::fromModel($importRun)->toArray(),
                ])
                ->log('Users import submitted');

            $approvalRequest = $this->approvalRequests->handle(
                actor: $actor,
                action: PermissionKey::tyanc('users', 'import'),
                subject: $importRun,
                attributes: [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Users import'),
                        'subject_label' => $file->getClientOriginalName(),
                        'action_url' => route('tyanc.users.index', absolute: false),
                    ],
                ],
            );

            return [
                'import' => ImportRunData::fromModel($importRun->fresh(['creator', 'approvalRequests'])),
                'approval' => ApprovalRequestData::fromModel($approvalRequest, $actor),
            ];
        });
    }
}
