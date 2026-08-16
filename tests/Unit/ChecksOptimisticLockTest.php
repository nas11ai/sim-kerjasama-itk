<?php

use App\Models\FormSubmission;
use App\Support\Concerns\ChecksOptimisticLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

$subject = new class
{
    use ChecksOptimisticLock {
        assertNotStale as public;
    }
};

it('passes when the timestamp matches', function () use ($subject) {
    $submission = FormSubmission::factory()->create();

    $subject->assertNotStale($submission, $submission->updated_at->toIso8601String());
})->throwsNoExceptions();

it('rejects a stale timestamp with 409', function () use ($subject) {
    $submission = FormSubmission::factory()->create();

    try {
        $subject->assertNotStale($submission, now()->subDay()->toIso8601String());
        $this->fail('Seharusnya melempar HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});

it('rejects a missing timestamp', function () use ($subject) {
    $submission = FormSubmission::factory()->create();

    try {
        $subject->assertNotStale($submission, null);
        $this->fail('Seharusnya melempar HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});
