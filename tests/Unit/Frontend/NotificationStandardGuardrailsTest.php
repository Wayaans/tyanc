<?php

declare(strict_types=1);

/**
 * @return array<int, array{relative: string, contents: string}>
 */
function notificationGuardrailFiles(string $directory, array $extensions): array
{
    $files = [];
    $basePath = base_path().DIRECTORY_SEPARATOR;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        if (! in_array($file->getExtension(), $extensions, true)) {
            continue;
        }

        $path = $file->getPathname();

        $files[] = [
            'relative' => str_replace($basePath, '', $path),
            'contents' => (string) file_get_contents($path),
        ];
    }

    usort(
        $files,
        fn (array $left, array $right): int => $left['relative'] <=> $right['relative'],
    );

    return $files;
}

it('allows direct vue-sonner imports only inside shared notification wrapper', function (): void {
    $allowedImports = [
        'resources/js/components/ui/sonner/Sonner.vue',
        'resources/js/lib/notify.ts',
    ];

    $violations = collect(notificationGuardrailFiles(resource_path('js'), ['ts', 'vue']))
        ->reject(fn (array $file): bool => in_array($file['relative'], $allowedImports, true))
        ->filter(fn (array $file): bool => preg_match('/from\s+[\'\"]vue-sonner[\'\"]/', $file['contents']) === 1)
        ->pluck('relative')
        ->all();

    expect($violations)->toBe([]);
});

it('forbids raw status flashes in the http layer', function (): void {
    $statusFlashPattern = '/->with\([\'\"]status[\'\"]|->flash\([\'\"]status[\'\"]|session\(\)->flash\([\'\"]status[\'\"]|session\(\)->get\([\'\"]status[\'\"]/';

    $violations = collect(notificationGuardrailFiles(app_path('Http'), ['php']))
        ->filter(fn (array $file): bool => preg_match($statusFlashPattern, $file['contents']) === 1)
        ->pluck('relative')
        ->all();

    expect($violations)->toBe([]);
});

it('forbids legacy inline transient success patterns in frontend surfaces', function (): void {
    $violations = [];

    foreach (notificationGuardrailFiles(resource_path('js'), ['vue', 'ts']) as $file) {
        if (preg_match('/v-show\s*=\s*[\'\"]recentlySuccessful[\'\"]/', $file['contents']) === 1) {
            $violations[] = $file['relative'].'#recentlySuccessful-vshow';
        }

        if (str_starts_with($file['relative'], 'resources/js/pages/')
            && preg_match('/status\?\s*:\s*string\s*\|\s*null/', $file['contents']) === 1) {
            $violations[] = $file['relative'].'#status-prop';
        }
    }

    expect($violations)->toBe([]);
});
