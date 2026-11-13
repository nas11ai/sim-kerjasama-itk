<?php

use App\Data\View\SubmissionStatusData;
use App\Models\FormSubmission;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\UnderReview;

it('menyalin key, label, dan variant dari state', function (string $state, string $key, string $label, string $variant) {
    $data = SubmissionStatusData::fromState(new $state(new FormSubmission));

    expect($data->key)->toBe($key)
        ->and($data->label)->toBe($label)
        ->and($data->variant)->toBe($variant);
})->with([
    'under penilaian' => [UnderReview::class, 'under_review', 'Sedang Dinilai', 'info'],
    'rejected' => [Rejected::class, 'rejected', 'Ditolak', 'destructive'],
    'draft' => [Draft::class, 'draft', 'Draf', 'outline'],
    // Paket 2 P1 — keputusan dan "perlu tindakan peneliti" punya nada sendiri, tidak netral.
    'approved' => [Approved::class, 'approved', 'Disetujui', 'success'],
    'needs revision' => [NeedsRevision::class, 'needs_revision', 'Perlu Revisi', 'warning'],
]);
