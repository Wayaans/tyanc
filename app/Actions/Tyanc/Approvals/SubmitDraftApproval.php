<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Contracts\Approvals\DraftApprovalSubject;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class SubmitDraftApproval
{
    public function __construct(private CreateApprovalProposal $approvalRequests) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $actor,
        ApprovalRule $rule,
        string $permissionName,
        Model $subject,
        array $attributes = [],
    ): ApprovalRequest {
        if (! $subject instanceof DraftApprovalSubject) {
            throw ValidationException::withMessages([
                'approval' => __('Draft approval requires a revision-aware draft subject.'),
            ]);
        }

        return $this->approvalRequests->handle(
            actor: $actor,
            rule: $rule,
            permissionName: $permissionName,
            subject: $subject,
            attributes: $attributes,
            mode: ApprovalMode::Draft,
        );
    }
}
