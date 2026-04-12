<?php

declare(strict_types=1);

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\FileLibrary;
use App\Models\ImportRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

function documentPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function documentManager(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(documentPermission(...), $permissions));

    return $user;
}

it('renders the tyanc files page for authorized managers', function (): void {
    $manager = documentManager([
        PermissionKey::tyanc('files', 'viewany'),
    ]);

    $this->actingAs($manager)
        ->get(route('tyanc.files.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/files/Index')
            ->where('filesTable.meta.total', 0));
});

it('uploads image and non-image files to the shared tyanc library', function (): void {
    Storage::fake('public');

    $manager = documentManager([
        PermissionKey::tyanc('files', 'viewany'),
        PermissionKey::tyanc('files', 'upload'),
    ]);

    $response = $this->actingAs($manager)
        ->postJson(route('tyanc.files.store'), [
            'files' => [
                UploadedFile::fake()->image('team-photo.png'),
                UploadedFile::fake()->create('report.pdf', 120, 'application/pdf'),
            ],
        ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'files')
        ->assertJsonPath('files.0.uploaded_by_name', $manager->name);

    expect($response->json('files.0.url'))->toStartWith('/storage/')
        ->and($response->json('files.0.preview_url'))->toStartWith('/storage/')
        ->and($response->json('files.1.url'))->toStartWith('/storage/');

    $library = FileLibrary::shared();
    $mediaItems = $library->getMedia(FileLibrary::FilesCollection);

    expect($mediaItems)->toHaveCount(2)
        ->and(Activity::query()->where('log_name', 'files')->where('description', 'Files uploaded')->exists())->toBeTrue();
});

it('deletes files from the shared tyanc library', function (): void {
    Storage::fake('public');

    $manager = documentManager([
        PermissionKey::tyanc('files', 'viewany'),
        PermissionKey::tyanc('files', 'upload'),
        PermissionKey::tyanc('files', 'delete'),
    ]);

    $library = FileLibrary::shared();
    $media = $library
        ->addMedia(UploadedFile::fake()->create('obsolete.txt', 8, 'text/plain'))
        ->withCustomProperties([
            'uploaded_by_id' => (string) $manager->id,
            'uploaded_by_name' => $manager->name,
        ])
        ->toMediaCollection(FileLibrary::FilesCollection);

    $this->actingAs($manager)
        ->deleteJson(route('tyanc.files.destroy', $media))
        ->assertNoContent();

    expect($library->fresh()->getMedia(FileLibrary::FilesCollection))->toHaveCount(0)
        ->and(Activity::query()->where('log_name', 'files')->where('description', 'File deleted')->exists())->toBeTrue();
});

it('forbids file management without the correct permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tyanc.files.index'))
        ->assertForbidden();
});

it('shares disabled import and export feature flags on users and activity log pages', function (): void {
    $manager = documentManager([
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::tyanc('users', 'import'),
        PermissionKey::tyanc('users', 'export'),
        PermissionKey::tyanc('activity_log', 'view'),
        PermissionKey::tyanc('activity_log', 'export'),
    ]);

    $this->actingAs($manager)
        ->get(route('tyanc.users.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/users/Index')
            ->where('features.imports_enabled', false)
            ->where('features.exports_enabled', false));

    $this->actingAs($manager)
        ->get(route('tyanc.activity-log.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/activity-log/Index')
            ->where('features.exports_enabled', false));
});

it('returns not found for user imports while the feature is disabled', function (): void {
    Storage::fake('public');

    $requester = documentManager([
        PermissionKey::tyanc('users', 'import'),
    ]);

    $this->actingAs($requester)
        ->post(route('tyanc.users.import.store'), [
            'file' => UploadedFile::fake()->create(
                'users.xlsx',
                32,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
            'request_note' => 'Please review this onboarding import.',
        ], ['Accept' => 'application/json'])
        ->assertNotFound();

    expect(ImportRun::query()->count())->toBe(0)
        ->and(ApprovalRequest::query()->count())->toBe(0);
});

it('rejects an approval request without mutating the governed import run', function (): void {
    $reviewerRole = Role::query()->create([
        'name' => 'Document Workflow Reviewers',
        'guard_name' => 'web',
        'level' => 60,
    ]);

    $approver = documentManager([
        PermissionKey::cumpu('approvals', 'viewany'),
        PermissionKey::cumpu('approvals', 'reject'),
        PermissionKey::tyanc('users', 'import'),
    ]);
    $approver->assignRole($reviewerRole);

    $requester = User::factory()->create();
    $importRun = ImportRun::factory()->for($requester, 'creator')->create();
    $approvalRule = ApprovalRule::factory()
        ->forPermission(PermissionKey::tyanc('users', 'import'))
        ->enabled()
        ->create();

    $step = $approvalRule->steps()->create([
        'role_id' => $reviewerRole->id,
        'step_order' => 1,
        'label' => 'Import review',
    ]);

    $approvalRequest = ApprovalRequest::factory()->for($approvalRule, 'rule')->create([
        'subject_type' => ImportRun::class,
        'subject_id' => $importRun->id,
        'requested_by_id' => $requester->id,
    ]);

    $approvalRequest->assignments()->create([
        'approval_rule_step_id' => $step->id,
        'assigned_to_id' => $approver->id,
    ]);

    $this->actingAs($approver)
        ->patchJson(route('cumpu.approvals.reject', $approvalRequest), [
            'review_note' => 'The file needs corrections first.',
        ])
        ->assertOk()
        ->assertJsonPath('approval.status', ApprovalRequest::StatusRejected);

    expect($approvalRequest->fresh()->status)->toBe(ApprovalRequest::StatusRejected)
        ->and($importRun->fresh()->status)->toBe(ImportRun::StatusPendingApproval)
        ->and($importRun->fresh()->failure_message)->toBeNull();
});

it('returns not found for exports while the feature is disabled', function (): void {
    $manager = documentManager([
        PermissionKey::tyanc('users', 'export'),
        PermissionKey::tyanc('activity_log', 'export'),
    ]);

    $this->actingAs($manager)
        ->get(route('tyanc.users.export'))
        ->assertNotFound();

    $this->actingAs($manager)
        ->get(route('tyanc.users.export.pdf'))
        ->assertNotFound();

    $this->actingAs($manager)
        ->get(route('tyanc.activity-log.export'))
        ->assertNotFound();

    $this->actingAs($manager)
        ->get(route('tyanc.activity-log.export.pdf'))
        ->assertNotFound();
});
