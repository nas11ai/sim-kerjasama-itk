<?php

// tests/Unit/S3StorageServiceTest.php

use App\Services\Storage\S3StorageService;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->service = new S3StorageService('s3');
});

it('implements the storage contract', function () {
    expect($this->service)->toBeInstanceOf(StorageService::class);
});

it('stores the file under a uuid name and keeps the extension', function () {
    $result = $this->service->upload(UploadedFile::fake()->create('proposal.pdf', 12), 'proposals');

    expect($result->path)->toStartWith('proposals/')
        ->and($result->path)->toEndWith('.pdf')
        ->and($result->path)->not->toContain('proposal.pdf');

    Storage::disk('s3')->assertExists($result->path);
});

it('deletes a stored file', function () {
    $result = $this->service->upload(UploadedFile::fake()->create('a.pdf', 1), 'proposals');

    $this->service->delete($result->path);

    Storage::disk('s3')->assertMissing($result->path);
});

it('generates a temporary url when an expiry is given', function () {
    $result = $this->service->upload(UploadedFile::fake()->create('a.pdf', 1), 'proposals');

    $url = $this->service->getUrl($result->path, 5);

    expect($url)->toContain('expiration=')
        ->and($url)->not->toBe($this->service->getUrl($result->path));
});

// I4 — path yang dikembalikan dulu dihitung terpisah dari path yang
// dipakai putFileAs() untuk menyimpan; untuk prefix kosong/'/' keduanya
// menyimpang (leading slash), sehingga FileResult::$path menunjuk ke key
// yang tidak ada filenya.
it('returns the path the file actually occupies when the prefix is empty or just a slash', function (string $prefix) {
    $result = $this->service->upload(UploadedFile::fake()->create('a.pdf', 1), $prefix);

    expect($result->path)->not->toStartWith('/');
    Storage::disk('s3')->assertExists($result->path);
})->with(['', '/']);
