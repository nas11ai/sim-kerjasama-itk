<?php

// tests/Feature/Review/ReviewerAssignmentListTest.php
//
// BR-SM-07, BR-REV-10 — daftar tugas reviewer hanya memuat penugasan yang berlaku.
// BR-REV-05 — "selesai" berarti jawabannya sudah dikirim; "terlambat" berarti lewat
// tenggat dan belum dikirim. Statistik dan filter harus memakai definisi yang sama.

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;

function listedAssignment(
    Reviewer $reviewer,
    string $formTitle,
    ?DateTimeInterface $dueDate = null,
    ?string $responseStatus = null,
    string $submissionFormTitle = 'Hibah Penelitian',
): ReviewerFormAssignment {
    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $submission = FormSubmission::factory()->create([
        'form_id' => Form::factory()->create(['title' => $submissionFormTitle])->id,
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => User::factory()->create()->id,
        'status' => UnderReview::class,
    ]);
    $submissionReviewer = SubmissionReviewer::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'evaluation_status' => 'pending',
        'status' => 'active',
    ]);
    $form = ReviewEvaluationForm::create([
        'title' => $formTitle,
        'form_phase_detail_id' => $detail->id,
        'is_active' => true,
        'is_required' => true,
        'order' => 1,
    ]);

    $assignment = ReviewerFormAssignment::create([
        'submission_reviewer_id' => $submissionReviewer->id,
        'review_evaluation_form_id' => $form->id,
        'is_required' => true,
        'due_date' => $dueDate,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    if ($responseStatus !== null) {
        ReviewFormResponse::create([
            'reviewer_form_assignment_id' => $assignment->id,
            'status' => $responseStatus,
        ]);
    }

    return $assignment;
}

/** @return array{0: User, 1: array<string, ReviewerFormAssignment>} */
function reviewerWithListedAssignments(): array
{
    FieldType::firstOrCreate(['name' => 'text']);
    $user = User::factory()->create();
    $reviewer = Reviewer::factory()->create(['user_id' => $user->id]);

    $assignments = [
        'open' => listedAssignment($reviewer, 'Substansi'),
        'done' => listedAssignment($reviewer, 'Anggaran', now()->subDay(), 'submitted'),
        'late' => listedAssignment($reviewer, 'Metodologi', now()->subDay(), 'draft'),
        'soon' => listedAssignment($reviewer, 'Luaran', now()->addWeek()),
    ];

    $stranger = Reviewer::factory()->create(['user_id' => User::factory()->create()->id]);
    listedAssignment($stranger, 'Milik Orang Lain');

    return [$user, $assignments];
}

/** @param  list<int>  $expected */
function assertListedIds(Assert $page, array $expected): void
{
    $ids = collect($page->toArray()['props']['assignments']['data'])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect($expected)->sort()->values()->all());
}

it('menghitung statistik tugas reviewer dengan definisi selesai dan terlambat yang sama — BR-REV-05', function () {
    [$user] = reviewerWithListedAssignments();

    $this->actingAs($user)
        ->get(route('reviewer.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats', ['total' => 4, 'pending' => 3, 'completed' => 1, 'overdue' => 1])
            ->has('assignments.data', 4));
});

it('menyaring daftar tugas menurut status', function (string $status, array $expectedKeys) {
    [$user, $assignments] = reviewerWithListedAssignments();
    $expected = array_map(fn (string $key) => $assignments[$key]->id, $expectedKeys);

    $this->actingAs($user)
        ->get(route('reviewer.assignments.index', ['status' => $status]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($expected, $status) {
            assertListedIds($page, $expected);
            $page->where('filters.status', $status)
                // Statistik tidak ikut tersaring.
                ->where('stats.total', 4);
        });
})->with([
    'pending' => ['pending', ['open', 'late', 'soon']],
    'completed' => ['completed', ['done']],
    'overdue' => ['overdue', ['late']],
]);

it('mencari tugas berdasarkan judul form evaluasi atau judul form pengajuan tanpa peduli huruf besar', function () {
    [$user, $assignments] = reviewerWithListedAssignments();

    $this->actingAs($user)
        ->get(route('reviewer.assignments.index', ['search' => 'metodo']))
        ->assertInertia(fn (Assert $page) => assertListedIds($page, [$assignments['late']->id]));

    $this->actingAs($user)
        ->get(route('reviewer.assignments.index', ['search' => 'HIBAH PENELITIAN']))
        ->assertInertia(function (Assert $page) {
            expect($page->toArray()['props']['assignments']['data'])->toHaveCount(4);
        });
});

it('mengurutkan tugas terlambat lebih dulu, lalu bertenggat, lalu tanpa tenggat', function () {
    [$user, $assignments] = reviewerWithListedAssignments();

    $this->actingAs($user)
        ->get(route('reviewer.assignments.index'))
        ->assertInertia(function (Assert $page) use ($assignments) {
            $ids = collect($page->toArray()['props']['assignments']['data'])->pluck('id')->all();

            // `done` dan `late` sama-sama lewat tenggat; `soon` bertenggat; `open` tanpa tenggat.
            expect(array_slice($ids, 2))->toBe([$assignments['soon']->id, $assignments['open']->id])
                ->and(array_slice($ids, 0, 2))
                ->toContain($assignments['done']->id, $assignments['late']->id);
        });
});

it('menampilkan daftar kosong untuk pengguna yang bukan reviewer', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reviewer.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments', [])
            ->where('stats', ['total' => 0, 'pending' => 0, 'completed' => 0, 'overdue' => 0]));
});
