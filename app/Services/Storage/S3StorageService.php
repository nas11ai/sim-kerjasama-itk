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

        // I4 — putFileAs() men-trim sendiri path yang diberikan dan
        // mengembalikan path aslinya; jangan hitung ulang path secara
        // terpisah, karena untuk $path kosong/'/' hasilnya menyimpang dari
        // lokasi file yang sesungguhnya (leading slash yang tidak dipakai
        // putFileAs).
        $storedPath = Storage::disk($this->disk)->putFileAs(trim($path, '/'), $file, $name);

        if ($storedPath === false) {
            throw new \RuntimeException("Gagal menyimpan file ke disk '{$this->disk}'.");
        }

        return new FileResult($storedPath, $this->getUrl($storedPath));
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
