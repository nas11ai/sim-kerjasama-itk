<?php

// tests/Feature/Review/SubmissionStatusEndpointRemovedTest.php
//
// BR-REV-05/06 — hasil pengajuan dihitung otomatis; BR-REV-07 — penolakan hanya
// lewat aksi eksplisit Operator. Tidak ada jalur untuk menyetel status sendiri.

use Illuminate\Support\Facades\Route;

it('tidak menyediakan jalur menyetel status pengajuan secara langsung', function () {
    expect(Route::has('submissions.update-status'))->toBeFalse();
});
