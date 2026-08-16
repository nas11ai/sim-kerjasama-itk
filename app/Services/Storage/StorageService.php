<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;

interface StorageService
{
    public function upload(UploadedFile $file, string $path): FileResult;

    public function delete(string $path): void;

    public function getUrl(string $path, ?int $expiresInMinutes = null): string;
}
