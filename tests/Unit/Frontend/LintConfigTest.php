<?php

declare(strict_types=1);

it('ignores .agents in tsconfig and vite lint config', function (): void {
    $tsconfig = (string) file_get_contents(base_path('tsconfig.json'));
    $viteConfig = (string) file_get_contents(base_path('vite.config.ts'));

    expect($tsconfig)
        ->toContain('".agents/**/*"')
        ->toContain('"**/.agents/**"')
        ->and($viteConfig)
        ->toContain("ignorePatterns: ['vite.config.ts', '.agents/**', '**/.agents/**']");
});
