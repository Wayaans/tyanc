<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;

function exportPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function exportManager(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(exportPermission(...), $permissions));

    return $user;
}

dataset('tyanc pdf exports', [
    'users report' => [
        'routeName' => 'tyanc.users.export.pdf',
        'permission' => PermissionKey::tyanc('users', 'export'),
        'filename' => 'users-report.pdf',
    ],
    'activity summary' => [
        'routeName' => 'tyanc.activity-log.export.pdf',
        'permission' => PermissionKey::tyanc('activity_log', 'export'),
        'filename' => 'activity-summary.pdf',
    ],
]);

it('uses dompdf as the default pdf driver', function (): void {
    expect(config('laravel-pdf.driver'))->toBe('dompdf');
});

it('downloads tyanc pdf exports', function (string $routeName, string $permission, string $filename): void {
    $this->seed(AppRegistrySeeder::class);

    config()->set('tyanc.features.exports_enabled', true);

    $manager = exportManager([$permission]);

    $response = $this->actingAs($manager)->get(route($routeName));

    $response
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($filename);

    expect((string) $response->baseResponse->getContent())->toStartWith('%PDF-');
})->with('tyanc pdf exports');
