<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3StorageService implements StorageService
{
    public function __construct(private readonly string $disk = 's3') {}

    public function upload(UploadedFile $file, string $path): FileResult
    {
        $extension = $file->getClientOriginalExtension();
        $name = $extension === '' ? Str::uuid()->toString() : Str::uuid()->toString().'.'.$extension;
        $fullPath = trim($path, '/').'/'.$name;

        Storage::disk($this->disk)->putFileAs(trim($path, '/'), $file, $name);

        return new FileResult($fullPath, $this->getUrl($fullPath));
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk)->delete($path);
    }

    public function getUrl(string $path, ?int $expiresInMinutes = null): string
    {
        if ($expiresInMinutes === null) {
            return Storage::disk($this->disk)->url($path);
        }

        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes($expiresInMinutes),
        );
    }
}
