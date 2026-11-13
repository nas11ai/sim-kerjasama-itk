<?php

// Riwayat tahun lalu per set (docs/design/2026-11-13-seeder-data-uji-qa-design.md, "riwayat Periode"):
// lahir pada jam tahun lalu dan dituntaskan sampai monev dan luaran.

use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\SubmissionYear;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\Support\PrivateUploadDisk;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Qa\QaAccounts;
use Database\Seeders\Qa\QaCatalog;
use Database\Seeders\Qa\QaHistory;
use Database\Seeders\Qa\QaSubmissionFlow;
use Illuminate\Support\Facades\Storage;

it('riwayat tahun lalu terhitung di tahun lalu dan tuntas', function () {
    Storage::fake(PrivateUploadDisk::NAME);
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $catalog = QaCatalog::build($admin);
    $set = QaAccounts::build(3);
    $last = now()->year - 1;

    $history = QaHistory::build($set, new QaSubmissionFlow($catalog));

    expect(array_keys($history))->toBe([1, 2, 3, 4]);

    foreach ($history as $n => $submission) {
        expect($submission->submission_period_id)->toBe($catalog->periods['last']->id)
            ->and($submission->created_at->year)->toBe($last)
            // Review Focus 2 — isian form terlihat oleh pengajuan tahun lalu.
            ->and($submission->title)->toStartWith('[QA3] ')
            ->and($submission->submitted_by)->toBe($set->researchers[$n]->id);
    }

    foreach ([1, 2, 3] as $n) {
        $report = $history[$n]->children()->sole();
        expect($history[$n]->status)->toBeInstanceOf(Approved::class)
            ->and($report->is_submitted)->toBeTrue()
            ->and($report->submissionReviewers()->where('evaluation_status', 'completed')->count())->toBe(1)
            ->and($history[$n]->researchOutputs()->count())->toBe(1);
    }
    expect($history[4]->status)->toBeInstanceOf(Rejected::class);

    // Review Focus 5 — tahun statistik dibaca dari audit submit, dan jatuh di tahun lalu.
    $ids = array_map(fn (FormSubmission $s): int => $s->id, $history);
    expect(SubmissionYear::apply(FormSubmission::query()->whereKey($ids), $last)->count())->toBe(4)
        ->and(SubmissionYear::apply(FormSubmission::query()->whereKey($ids), now()->year)->count())->toBe(0);
});
