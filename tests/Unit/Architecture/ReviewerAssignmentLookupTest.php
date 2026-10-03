<?php

// tests/Unit/Architecture/ReviewerAssignmentLookupTest.php
//
// BR-SM-07, BR-REV-10 — tiga belas pencarian ad-hoc dulu lupa menyaring reviewer
// yang sudah diganti. Satu definisi hanya bertahan kalau yang baru tak bisa lahir.

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

it('penugasan reviewer atas satu pengajuan dicari lewat ActiveReviewerAssignment', function () {
    $violations = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php')->sortByName() as $file) {
        $path = 'app/'.$file->getRelativePathname();

        if ($path === 'app/Queries/Review/ActiveReviewerAssignment.php') {
            continue;
        }

        preg_match_all('/SubmissionReviewer::(?:query\(\)\s*->\s*)?where\(.*?;/s', $file->getContents(), $found);

        foreach ($found[0] as $statement) {
            $byReviewer = str_contains($statement, "'reviewer_id' =>")
                || preg_match("/where\(\s*'reviewer_id'/", $statement) === 1;

            // Mencari satu reviewer atas satu pengajuan tanpa menyaring yang sudah
            // diganti. Yang hanya menyaring per reviewer, atau sengaja menghitung
            // semua penugasan (BR-REV-03), tidak termasuk.
            if ($byReviewer && str_contains($statement, 'form_submission_id') && !str_contains($statement, "'active'")) {
                $violations[] = $path;
            }
        }
    }

    expect($violations)->toBe([], "Pakai ActiveReviewerAssignment::run():\n".implode("\n", array_unique($violations)));
});
