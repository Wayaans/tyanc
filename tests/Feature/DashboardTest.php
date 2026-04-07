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
            ->component('Dashboard')
            ->where('currentApp', 'tyanc')
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
            ->component('Dashboard')
            ->where('currentApp', 'demo')
            ->where('sidebarNavigation.apps.1.href', '/demo/dashboard')
            ->where('sidebarNavigation.menu.0.href', '/demo/dashboard'));
});
