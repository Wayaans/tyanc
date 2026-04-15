<?php

declare(strict_types=1);

use App\Actions\Tyanc\Files\SyncManagedFiles;
use App\Models\FileLibrary;
use App\Models\ManagedFile;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function filePermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function fileManager(array $permissions): User
{
    test()->seed(AppRegistrySeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo(array_map(filePermission(...), $permissions));

    return $user;
}

it('indexes shared library media and raw avatars in the tyanc explorer', function (): void {
    Storage::fake('public');

    $manager = fileManager([
        PermissionKey::tyanc('files', 'viewany'),
    ]);

    $avatarOwner = User::factory()->create([
        'name' => 'Avatar Owner',
        'avatar' => 'avatars/avatar-owner.png',
    ]);
    Storage::disk('public')->put('avatars/avatar-owner.png', 'avatar-binary');

    $library = FileLibrary::shared();
    $library
        ->addMedia(UploadedFile::fake()->create('governance-guide.pdf', 16, 'application/pdf'))
        ->withCustomProperties([
            'app_key' => 'tyanc',
            'resource_key' => 'files',
            'folder_path' => 'tyanc/shared',
            'uploaded_by_id' => (string) $manager->id,
            'uploaded_by_name' => $manager->name,
        ])
        ->toMediaCollection(FileLibrary::FilesCollection);

    $this->actingAs($manager)
        ->get(route('tyanc.files.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/files/Index')
            ->where('filesTable.meta.total', 2)
            ->where('filesTable.rows', fn ($rows): bool => collect($rows)->pluck('folder_path')->contains('tyanc/shared')
                && collect($rows)->pluck('folder_path')->contains('tyanc/users/avatars'))
            ->where('filesTable.rows', fn ($rows): bool => collect($rows)->pluck('subject_label')->contains($avatarOwner->name))
            ->where('explorer.apps', fn ($apps): bool => collect($apps)
                ->contains(fn ($app): bool => $app['key'] === 'tyanc'
                    && collect($app['folders'])->pluck('path')->contains('tyanc/shared')
                    && collect($app['folders'])->pluck('path')->contains('tyanc/users/avatars'))));
});

it('streams and downloads managed files through tyanc routes', function (): void {
    Storage::fake('public');

    $manager = fileManager([
        PermissionKey::tyanc('files', 'viewany'),
        PermissionKey::tyanc('files', 'download'),
    ]);

    $avatarOwner = User::factory()->create([
        'avatar' => 'avatars/stream-me.txt',
    ]);
    Storage::disk('public')->put('avatars/stream-me.txt', 'streamed-content');

    resolve(SyncManagedFiles::class)->handle();

    $managedFile = ManagedFile::query()->firstWhere('relative_path', $avatarOwner->avatar);

    expect($managedFile)->toBeInstanceOf(ManagedFile::class);

    $streamResponse = $this->actingAs($manager)
        ->get(route('tyanc.files.show', $managedFile));

    $streamResponse->assertOk();

    expect($streamResponse->headers->get('content-type'))->toContain('text/plain');

    $downloadResponse = $this->actingAs($manager)
        ->get(route('tyanc.files.download', $managedFile));

    $downloadResponse->assertOk();
    expect($downloadResponse->headers->get('content-disposition'))->toContain('attachment;')
        ->and($downloadResponse->headers->get('content-disposition'))->toContain('stream-me.txt');
});

it('deletes tracked avatar files and clears the owning user reference', function (): void {
    Storage::fake('public');

    $manager = fileManager([
        PermissionKey::tyanc('files', 'viewany'),
        PermissionKey::tyanc('files', 'delete'),
    ]);

    $avatarOwner = User::factory()->create([
        'avatar' => 'avatars/delete-me.png',
    ]);
    Storage::disk('public')->put('avatars/delete-me.png', 'delete-me');

    resolve(SyncManagedFiles::class)->handle();

    $managedFile = ManagedFile::query()->firstWhere('relative_path', $avatarOwner->avatar);

    expect($managedFile)->toBeInstanceOf(ManagedFile::class)
        ->and($managedFile?->is_deletable)->toBeTrue();

    $this->actingAs($manager)
        ->deleteJson(route('tyanc.files.destroy', $managedFile))
        ->assertNoContent();

    Storage::disk('public')->assertMissing('avatars/delete-me.png');

    expect($avatarOwner->fresh()->avatar)->toBeNull()
        ->and(ManagedFile::query()->whereKey($managedFile?->id)->exists())->toBeFalse();
});
