<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Files;

use Spatie\LaravelData\Data;

final class FileExplorerFolderData extends Data
{
    public function __construct(
        public string $path,
        public string $label,
        public int $total_files,
    ) {}
}
