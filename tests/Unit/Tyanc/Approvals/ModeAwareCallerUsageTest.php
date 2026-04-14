<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('does not allow production actions to depend on SubmitGovernedAction directly', function (): void {
    $violations = collect(File::allFiles(app_path('Actions')))
        ->reject(fn ($file): bool => str_contains($file->getRealPath(), '/Actions/Tyanc/Approvals/'))
        ->filter(fn ($file): bool => $file->getExtension() === 'php')
        ->map(fn ($file): ?string => str_contains(File::get($file->getRealPath()), 'SubmitGovernedAction')
            ? str_replace(base_path().'/', '', $file->getRealPath())
            : null)
        ->filter()
        ->values()
        ->all();

    expect($violations)->toBe([]);
});
