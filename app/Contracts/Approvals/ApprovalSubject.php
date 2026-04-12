<?php

declare(strict_types=1);

namespace App\Contracts\Approvals;

interface ApprovalSubject
{
    public function approvalAppKey(): string;

    public function approvalResourceKey(): string;

    public function approvalSubjectLabel(): string;

    /**
     * @return array<string, mixed>
     */
    public function approvalSubjectSnapshot(): array;
}
