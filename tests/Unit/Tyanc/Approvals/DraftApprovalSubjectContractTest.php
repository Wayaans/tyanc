<?php

declare(strict_types=1);

use App\Contracts\Approvals\DraftApprovalSubject;
use App\Models\User;
use App\Models\UserUpdateDraft;

it('implements the draft approval subject contract with a revision-aware snapshot', function (): void {
    $user = User::factory()->create([
        'name' => 'Draft Target',
        'email' => 'draft-target@example.com',
    ]);

    $draft = UserUpdateDraft::factory()
        ->for($user, 'user')
        ->for(User::factory(), 'creator')
        ->create([
            'revision' => 5,
            'payload' => [
                'name' => 'Updated Draft Target',
                'email' => 'updated-draft-target@example.com',
                'roles' => [],
                'permissions' => [],
                'password' => 'secret-password',
            ],
            'changed_fields' => ['name', 'email', 'password'],
        ]);

    expect($draft)->toBeInstanceOf(DraftApprovalSubject::class)
        ->and($draft->approvalSubjectRevision())->toBe('5')
        ->and($draft->approvalSubjectLabel())->toBe('Update Draft Target')
        ->and($draft->approvalSubjectSnapshot())
        ->toMatchArray([
            'id' => (string) $draft->id,
            'user_id' => (string) $user->id,
            'revision' => '5',
            'changed_fields' => ['name', 'email', 'password'],
            'proposed' => [
                'name' => 'Updated Draft Target',
                'email' => 'updated-draft-target@example.com',
                'roles' => [],
                'permissions' => [],
                'password' => 'Updated',
            ],
        ]);
});
