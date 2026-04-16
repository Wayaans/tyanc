<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FileLibrary;
use App\Models\ImportRun;
use App\Models\ManagedFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagedFile>
 */
final class ManagedFileFactory extends Factory
{
    public function definition(): array
    {
        $fileName = sprintf('%s.%s', fake()->slug(), fake()->randomElement(['png', 'pdf', 'txt']));
        $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: null;
        $directoryPath = fake()->randomElement([
            'tyanc/shared',
            'tyanc/users/avatars',
            'unassigned/root',
        ]);
        $relativePath = sprintf('%s/%s', $directoryPath, $fileName);
        $mimeType = match ($extension) {
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'text/plain',
        };

        return [
            'disk' => ManagedFile::PublicDisk,
            'source' => fake()->randomElement([
                ManagedFile::SourceMediaLibrary,
                ManagedFile::SourcePublicDisk,
            ]),
            'app_key' => str($directoryPath)->before('/')->value() ?: ManagedFile::UnassignedAppKey,
            'resource_key' => fake()->randomElement(['files', 'users', 'settings']),
            'folder_path' => $directoryPath,
            'relative_path' => $relativePath,
            'directory_path' => $directoryPath,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'mime_group' => str($mimeType)->before('/')->value(),
            'size_bytes' => fake()->numberBetween(1024, 1024 * 512),
            'collection_name' => fake()->optional()->randomElement(['library_files', 'app_logo', 'source_file']),
            'media_id' => fake()->optional()->numberBetween(1, 1000),
            'subject_type' => fake()->optional()->randomElement([
                FileLibrary::class,
                User::class,
                ImportRun::class,
            ]),
            'subject_id' => fake()->optional()->uuid(),
            'subject_label' => fake()->optional()->words(2, true),
            'uploaded_by_id' => fake()->optional()->uuid(),
            'uploaded_by_name' => fake()->optional()->name(),
            'custom_properties' => null,
            'is_deletable' => fake()->boolean(),
            'uploaded_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'last_modified_at' => now()->subHours(fake()->numberBetween(1, 24)),
            'last_seen_at' => now(),
        ];
    }
}
