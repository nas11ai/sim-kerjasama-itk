<?php

// Union SubmissionStatusKey di frontend harus sama dengan state backend,
// supaya status baru tidak lolos vue-tsc tanpa tampil di UI.

use App\States\Submission\SubmissionStatus;
use Tests\TestCase;

uses(TestCase::class);

it('menjaga types/submission-status.ts sinkron dengan app/States/Submission', function () {
    $source = (string) file_get_contents(resource_path('js/types/submission-status.ts'));
    preg_match('/type SubmissionStatusKey\s*=([^\n]*(?:\n\s*\|[^\n]*)*)/', $source, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1] ?? '', $keys);

    $frontend = collect($keys[1])->sort()->values()->all();
    $backend = SubmissionStatus::all()
        ->map(fn (string $class) => $class::$name)
        ->sort()->values()->all();

    expect($frontend)->toBe($backend, 'types/submission-status.ts tidak sinkron dengan app/States/Submission');
});
