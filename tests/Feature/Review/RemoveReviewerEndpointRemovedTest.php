<?php

// tests/Feature/Review/RemoveReviewerEndpointRemovedTest.php
//
// BR-REV-10 — reviewer diganti, tidak dihapus: record lamanya tetap sebagai sejarah.
// Satu-satunya jalur yang melepas reviewer adalah ReplaceReviewer.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan jalur menghapus reviewer dari pengajuan', function () {
    expect(Route::has('admin.submissions.remove-reviewer'))->toBeFalse();
});
