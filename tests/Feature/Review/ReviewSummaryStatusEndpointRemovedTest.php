<?php

// tests/Feature/Review/ReviewSummaryStatusEndpointRemovedTest.php
//
// BR-REV-13, [REV-DISCUSS-04] — ulir hanya ditutup reviewer lewat
// ResolveReviewSummary. Jalur kedua mengizinkan Operator menutup dan siapa pun
// membuka kembali, dua hal yang tidak dikenal domain.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan jalur kedua untuk mengubah status ulir', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'review-summaries.update-status',
    'admin.review-summaries.update-status',
    'reviewer.review-summaries.update-status',
]);
