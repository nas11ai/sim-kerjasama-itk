<?php

use App\Models\FormSubmission;
use App\Support\OptimisticLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('passes when the timestamp matches', function () {
    $submission = FormSubmission::factory()->create();

    OptimisticLock::assertNotStale($submission, $submission->updated_at->toIso8601String());
})->throwsNoExceptions();

it('rejects a stale timestamp with 409', function () {
    $submission = FormSubmission::factory()->create();

    try {
        OptimisticLock::assertNotStale($submission, now()->subDay()->toIso8601String());
        $this->fail('Seharusnya melempar HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});

// Cap waktu yang hilang bukan tabrakan penyuntingan melainkan permintaan yang
// cacat bentuknya, jadi ia 422 dan bukan 409. Klien yang memuat ulang halaman
// setiap kali menerima 409 akan berputar selamanya kalau keduanya disamakan.
it('[FE-LOCK-03] rejects a missing timestamp as a validation error', function () {
    $submission = FormSubmission::factory()->create();

    expect(fn () => OptimisticLock::assertNotStale($submission, null))
        ->toThrow(ValidationException::class);
});
