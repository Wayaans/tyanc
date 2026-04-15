<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Files;

use Spatie\LaravelData\Data;

final class FileExplorerData extends Data
{
    /**
     * @param  array<int, FileExplorerAppData>  $apps
     */
    public function __construct(
        public int $total_files,
        public int $total_size_bytes,
        public string $total_size_human,
        public int $app_count,
        public int $folder_count,
        public int $media_files,
        public int $public_files,
        public array $apps,
    ) {}
}
