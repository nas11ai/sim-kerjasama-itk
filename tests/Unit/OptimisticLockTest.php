<?php

use App\Models\FormSubmission;
use App\Support\OptimisticLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('rejects a missing timestamp', function () {
    $submission = FormSubmission::factory()->create();

    try {
        OptimisticLock::assertNotStale($submission, null);
        $this->fail('Seharusnya melempar HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});
