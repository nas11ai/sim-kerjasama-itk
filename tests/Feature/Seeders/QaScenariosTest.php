<?php

// Skenario pengajuan tahun ini per set (docs/design/2026-11-13-seeder-data-uji-qa-design.md, tabel #1–#12).
// Setiap baris dicapai lewat Action asli; test ini memastikan keadaan akhirnya memang yang dijanjikan.

use App\Enums\ReviewRoundKind;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Review\DecisionReadiness;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use App\Support\RequiredFieldGuard;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Qa\QaAccounts;
use Database\Seeders\Qa\QaCatalog;
use Database\Seeders\Qa\QaScenarios;
use Database\Seeders\Qa\QaSubmissionFlow;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

function auditedAs(FormSubmission $submission, string $description): bool
{
    return Activity::query()
        ->where('subject_type', $submission->getMorphClass())
        ->where('subject_id', $submission->id)
        ->where('description', $description)
        ->exists();
}

it('[QA-SEED] setiap baris skenario tercapai', function () {
    Storage::fake(config('filesystems.default'));
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $catalog = QaCatalog::build($admin);
    $set = QaAccounts::build(1);

    $s = QaScenarios::build($set, new QaSubmissionFlow($catalog));

    expect(array_keys($s))->toBe(range(1, 12))
        ->and($s[1]->status)->toBeInstanceOf(Draft::class)
        ->and($s[1]->last_completed_step)->toBe(2)
        ->and($s[2]->status)->toBeInstanceOf(Draft::class)
        ->and($s[2]->last_completed_step)->toBe(6)
        ->and($s[3]->status)->toBeInstanceOf(Submitted::class)
        ->and($s[3]->submissionReviewers()->count())->toBe(0)
        ->and($s[4]->status)->toBeInstanceOf(Submitted::class)
        ->and($s[4]->submissionReviewers()->active()->count())->toBe(1)
        ->and($s[5]->status)->toBeInstanceOf(UnderReview::class)
        ->and($s[5]->submissionReviewers()->where('evaluation_status', 'completed')->count())->toBe(1)
        ->and($s[6]->status)->toBeInstanceOf(UnderReview::class)
        ->and(DecisionReadiness::run($s[6])->ready)->toBeTrue()
        ->and($s[7]->status)->toBeInstanceOf(NeedsRevision::class)
        ->and($s[8]->status)->toBeInstanceOf(UnderReview::class)
        ->and($s[8]->runningReviewRound()?->kind)->toBe(ReviewRoundKind::Recheck)
        ->and(auditedAs($s[8], 'Pengajuan dikirim ulang setelah revisi'))->toBeTrue()
        ->and($s[9]->status)->toBeInstanceOf(Approved::class)
        ->and($s[9]->children()->sole()->is_submitted)->toBeFalse()
        ->and($s[9]->researchOutputs()->count())->toBe(1)
        ->and($s[9]->researchMembers()->count())->toBe(1)
        ->and($s[9]->budgetLineItems()->count())->toBe(2)
        ->and($s[10]->status)->toBeInstanceOf(Approved::class)
        ->and($s[10]->children()->sole()->is_submitted)->toBeTrue()
        ->and($s[11]->status)->toBeInstanceOf(Rejected::class)
        ->and($s[12]->status)->toBeInstanceOf(Withdrawn::class);

    // #2 siap dikirim: tidak ada isian wajib yang kosong (tidak throw).
    RequiredFieldGuard::assertComplete($s[2]);

    foreach (array_slice($s, 2, null, true) as $n => $submission) {
        expect(auditedAs($submission, 'Pengajuan disubmit'))->toBeTrue("skenario #{$n} tanpa audit submit")
            ->and($submission->title)->toStartWith('[QA1] ');
    }

    // Review Focus 4 — berkas proposal mendarat di disk yang dikonfigurasi (di sini disk palsu).
    expect(Storage::disk(config('filesystems.default'))->allFiles())->not->toBeEmpty();
});
