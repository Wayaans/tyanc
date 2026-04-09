<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('renders the tyanc dashboard', function (): void {
    $user = User::factory()->create();

    Http::fake([
        '*__inertia_ssr*' => Http::response([
            'head' => [],
            'body' => '',
        ]),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/Dashboard')
            ->where('currentApp', 'tyanc')
            ->where('summary.module_count', 6)
            ->where('modulesTable.meta.total', 6)
            ->where('sidebarNavigation.apps.0.href', '/tyanc/dashboard')
            ->where('sidebarNavigation.menu.0.href', '/tyanc/dashboard'));
});

it('renders the demo dashboard', function (): void {
    $user = User::factory()->create();

    Http::fake([
        '*__inertia_ssr*' => Http::response([
            'head' => [],
            'body' => '',
        ]),
    ]);

    $response = $this->actingAs($user)->get(route('demo.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('demo/Dashboard')
            ->where('currentApp', 'demo')
            ->where('examplesTable.meta.total', 5)
            ->where('sidebarNavigation.apps.1.href', '/demo/dashboard')
            ->where('sidebarNavigation.menu.0.href', '/demo/dashboard'));
});

it('applies tyanc dashboard query string filters and sorting server-side', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('dashboard', [
            'filter' => [
                'status' => 'Monitoring',
            ],
            'sort' => ['-health_score'],
            'per_page' => 5,
        ]))
        ->assertOk()
        ->assertJsonPath('modulesTable.meta.total', 2)
        ->assertJsonPath('modulesTable.query.filter.status', 'Monitoring')
        ->assertJsonPath('modulesTable.query.sort.0', '-health_score')
        ->assertJsonPath('modulesTable.rows.0.name', 'Brand runtime')
        ->assertJsonPath('modulesTable.rows.1.name', 'Demo sandbox');
});
