<?php

declare(strict_types=1);

use App\Models\User;

it('redirects the shared dashboard entrypoint to the tyanc dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/'.mb_trim((string) config('tyanc.admin_path'), '/').'/dashboard');
});

it('serves the api v1 status endpoint on the configured api domain', function (): void {
    $response = $this->getJson(sprintf(
        'http://%s/%s/status',
        config('tyanc.api_domain'),
        mb_trim((string) config('tyanc.api_prefix'), '/'),
    ));

    $response->assertOk()
        ->assertJson([
            'app' => config('app.name'),
            'status' => 'ok',
            'version' => 'v1',
        ]);
});

it('does not expose the api v1 status endpoint on the root domain', function (): void {
    $this->getJson('/'.mb_trim((string) config('tyanc.api_prefix'), '/').'/status')
        ->assertNotFound();
});
