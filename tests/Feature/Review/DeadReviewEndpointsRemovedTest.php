<?php

// tests/Feature/Review/DeadReviewEndpointsRemovedTest.php
//
// Tiga endpoint JSON tanpa pemanggil. Daftar reviewer layar Operator dirakit
// adminShowSubmission, dan BR-REV-03 ditegakkan AssignReviewer.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan endpoint JSON review yang tak dipanggil', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.submissions.available-reviewers',
    'admin.submissions.available-evaluation-forms',
    'admin.submissions.evaluation-status',
]);
