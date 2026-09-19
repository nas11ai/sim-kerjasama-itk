<?php

// tests/Feature/Review/ReviewerSummaryActionTest.php

use App\Actions\Review\AssignReviewerSummary;
use App\Actions\Review\RemoveReviewerSummary;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;

it('membuat ReviewSummary terbuka untuk reviewer', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create();

    $summary = AssignReviewerSummary::run($submission, $reviewer->id, 'Metodologi perlu diperjelas.');

    expect($summary->status)->toBe('open')
        ->and($summary->reviewer_id)->toBe($reviewer->id)
        ->and($summary->summary_notes)->toBe('Metodologi perlu diperjelas.');
});

it('tidak membuat ReviewSummary kedua untuk reviewer yang sama', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create();

    $first = AssignReviewerSummary::run($submission, $reviewer->id, 'Catatan pertama.');
    $second = AssignReviewerSummary::run($submission->fresh(), $reviewer->id, 'Catatan kedua.');

    expect($second->id)->toBe($first->id)
        ->and(ReviewSummary::where('form_submission_id', $submission->id)->count())->toBe(1);
});

it('mencabut ReviewSummary milik reviewer', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::factory()->create();
    AssignReviewerSummary::run($submission, $reviewer->id, 'Catatan reviewer.');

    expect(RemoveReviewerSummary::run($submission->fresh(), $reviewer->id))->toBeTrue()
        ->and(ReviewSummary::where('form_submission_id', $submission->id)->exists())->toBeFalse();
});

it('mengembalikan false saat tidak ada yang dicabut', function () {
    $submission = FormSubmission::factory()->create();

    expect(RemoveReviewerSummary::run($submission, 999999))->toBeFalse();
});
