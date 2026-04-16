<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Files;

use Spatie\LaravelData\Data;

final class FileExplorerAppData extends Data
{
    /**
     * @param  array<int, FileExplorerFolderData>  $folders
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $total_files,
        public array $folders,
    ) {}
}
