<?php

// tests/Feature/Review/EvaluationProgressTest.php

use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\SubmissionReviewer;
use App\Queries\Review\EvaluationProgress;
use Illuminate\Support\Facades\DB;

function submissionWithStatuses(string ...$statuses): FormSubmission
{
    $submission = FormSubmission::factory()->create();

    foreach ($statuses as $status) {
        SubmissionReviewer::create([
            'form_submission_id' => $submission->id,
            'reviewer_id' => Reviewer::factory()->create()->id,
            'evaluation_status' => $status,
            'status' => 'active',
        ]);
    }

    return $submission->fresh();
}

it('menghitung sebaran status evaluasi', function () {
    $submission = submissionWithStatuses('completed', 'completed', 'pending', 'not_required');

    $progress = EvaluationProgress::run($submission);

    expect($progress->totalReviewers)->toBe(4)
        ->and($progress->completed)->toBe(2)
        ->and($progress->pending)->toBe(1)
        ->and($progress->notRequired)->toBe(1);
});

it('menghitung persentase dari selesai ditambah tidak diperlukan', function () {
    $progress = EvaluationProgress::run(submissionWithStatuses('completed', 'not_required', 'pending', 'pending'));

    expect($progress->completionPercentage)->toBe(50)
        ->and($progress->allCompleted)->toBeFalse();
});

it('menganggap pengajuan tanpa reviewer sudah seratus persen', function () {
    $progress = EvaluationProgress::run(FormSubmission::factory()->create());

    expect($progress->completionPercentage)->toBe(100)
        ->and($progress->allCompleted)->toBeTrue();
});

it('memakai satu kueri, bukan lima COUNT terpisah', function () {
    $submission = submissionWithStatuses('completed', 'pending');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        EvaluationProgress::run($submission);
        expect(DB::getQueryLog())->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
    }
});

it('tidak menghitung reviewer yang diganti sebagai pekerjaan tertunda', function () {
    $submission = submissionWithStatuses('completed', 'completed');

    SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => Reviewer::factory()->create()->id,
        'evaluation_status' => 'pending',
        'status' => 'replaced',
    ]);

    $progress = EvaluationProgress::run($submission->fresh());

    expect($progress->totalReviewers)->toBe(2)
        ->and($progress->pending)->toBe(0)
        ->and($progress->completionPercentage)->toBe(100)
        ->and($progress->allCompleted)->toBeTrue();
});
