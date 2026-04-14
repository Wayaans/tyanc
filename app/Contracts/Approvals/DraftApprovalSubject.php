<?php

declare(strict_types=1);

namespace App\Contracts\Approvals;

interface DraftApprovalSubject extends ApprovalSubject
{
    public function approvalSubjectRevision(): string;
}
