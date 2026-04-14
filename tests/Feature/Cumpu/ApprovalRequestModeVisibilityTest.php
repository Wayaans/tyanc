<?php

declare(strict_types=1);

use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUpdateDraft;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function approvalModeVisibilityPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function approvalModeVisibilityUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(approvalModeVisibilityPermission(...), $permissions));

    return $user;
}

it('shows approval mode and draft revision context on the request detail endpoint', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $requester = approvalModeVisibilityUser([
        PermissionKey::cumpu('my_requests', 'viewany'),
    ]);

    $reviewerRole = Role::query()->create([
        'name' => 'Draft Reviewers',
        'guard_name' => 'web',
        'level' => 80,
    ]);

    $managedUser = User::factory()->create();

    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'update'))
        ->draftMode()
        ->enabled()
        ->create();

    $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Draft review',
    ]);

    $draft = UserUpdateDraft::factory()->for($managedUser, 'user')->for($requester, 'creator')->create([
        'revision' => 3,
    ]);

    $approvalRequest = ApprovalRequest::factory()
        ->for($approvalRule, 'rule')
        ->for($draft, 'subject')
        ->draftMode('3')
        ->create([
            'action' => PermissionKey::tyanc('users', 'update'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'update',
            'requested_by_id' => $requester->id,
            'mode' => ApprovalMode::Draft->value,
            'subject_snapshot' => $draft->approvalSubjectSnapshot(),
        ]);

    $this->actingAs($requester)
        ->getJson(route('cumpu.approvals.show', $approvalRequest))
        ->assertOk()
        ->assertJsonPath('approval.mode', ApprovalMode::Draft->value)
        ->assertJsonPath('approval.subject_revision', '3')
        ->assertJsonPath('approval.subject_revision_matches_subject', true);

    $draft->forceFill(['revision' => 4])->save();

    $this->actingAs($requester)
        ->getJson(route('cumpu.approvals.show', $approvalRequest))
        ->assertOk()
        ->assertJsonPath('approval.subject_revision_matches_subject', false);
});
