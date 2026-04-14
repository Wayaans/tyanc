<?php

declare(strict_types=1);

namespace App\Data\Cumpu\Approvals;

use Spatie\LaravelData\Data;

final class GovernedActionStateData extends Data
{
    public function __construct(
        public string $action_key,
        public string $permission_name,
        public string $mode,
        public bool $approval_enabled,
        public bool $approval_required,
        public bool $bypasses_for_actor,
        public bool $has_usable_grant,
        public bool $has_blocking_request,
        public bool $has_committable_draft,
        public bool $has_stale_subject_revision,
        public bool $requires_draft_submission,
        public ?ApprovalContextRequestData $relevant_request,
    ) {}
}
