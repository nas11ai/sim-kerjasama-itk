<?php

namespace App\Services\Storage;

final readonly class FileResult
{
    public function __construct(
        public string $path,
        public string $url,
    ) {}
}
