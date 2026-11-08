<?php

// Kontrak RESEARCHER-02-05 (fe-v2-design §4.5): halaman revisi membaca ulir reviewer, hak balas
// (BR-REV-06), hak kirim ulang (BR-SM-03/04), dan field wajib yang masih kosong (BR-SM-02).

use App\Actions\Review\ReplaceReviewer;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\ResearchMember;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Models\UserProfile;
use App\States\Submission\NeedsRevision;
use App\States\Submission\UnderReview;
use Inertia\Testing\AssertableInertia as Assert;

it('ketua membuka Submission/RevisionPage', function () {
    [$owner, , $submission] = threadAuthorFixture(NeedsRevision::class);

    $this->actingAs($owner)
        ->get(route('user.submissions.revision', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Submission/RevisionPage')
            ->where('submission.id', $submission->id)
            ->has('threads', 1)
            ->where('threads.0.reviewer', 'Penilai Ulir')
            ->where('threads.0.isOpen', true)
            ->where('threads.0.notes', 'Perbaiki metodologi.')
            ->where('can.resubmit', true)
            ->where('can.discussReview', true)
            ->has('missingFields')
            ->has('accountBlock'));
});

it('[REV-DISCUSS-02] balasan ketua tampil di ulir yang sama', function () {
    [$owner, , $submission] = threadAuthorFixture(NeedsRevision::class);
    $summary = ReviewSummary::query()->where('form_submission_id', $submission->id)->sole();

    $this->actingAs($owner)
        ->post(route('review-comments.store', $summary), ['comment_text' => 'Metodologi sudah diperbaiki.'])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->get(route('user.submissions.revision', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.comments.0.body', 'Metodologi sudah diperbaiki.')
            ->where('threads.0.comments.0.author', $owner->name));
});

it('[REV-DISCUSS-06] pengganti me-resolve ulir warisan; peneliti tetap membaca penulis asli', function () {
    [$owner, $author, $submission] = threadAuthorFixture(NeedsRevision::class);
    $incomingUser = User::factory()->create(['name' => 'Pengganti']);
    $incoming = Reviewer::factory()->create([
        'user_id' => $incomingUser->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
    ReplaceReviewer::run($submission, Reviewer::query()->where('user_id', $author->id)->sole(), $incoming, null);

    $this->actingAs($incomingUser)
        ->post(route('review-summaries.resolve', ReviewSummary::query()->where('form_submission_id', $submission->id)->sole()))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('user.submissions.revision', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.isOpen', false)
            ->where('threads.0.reviewer', 'Penilai Ulir')
            ->where('threads.0.inheritedBy', 'Pengganti'));
});

it('co-investigator membaca tanpa hak balas dan kirim ulang', function () {
    [, , $submission] = threadAuthorFixture(NeedsRevision::class);
    $coInvestigator = User::factory()->create();
    ResearchMember::factory()->create([
        'form_submission_id' => $submission->id,
        'user_profile_id' => UserProfile::factory()->create(['user_id' => $coInvestigator->id])->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
        'status' => 'active',
    ]);

    $this->actingAs($coInvestigator)
        ->get(route('user.submissions.revision', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads', 1)
            ->where('can.discussReview', false)
            ->where('can.resubmit', false));
});

it('[SM-SUBMIT-19] field wajib kosong dikirim sebagai missingFields', function () {
    [$owner, , $submission] = threadAuthorFixture(NeedsRevision::class);
    $field = FormField::create([
        'form_id' => $submission->form_id,
        'label' => 'Proposal PDF',
        'is_required' => true,
        'field_type_id' => FieldType::firstOrCreate(['name' => 'file'])->id,
        'order' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('user.submissions.revision', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->has('missingFields', 1)
            ->where('missingFields.0.fieldId', $field->id)
            ->where('missingFields.0.label', 'Proposal PDF'));
});

it('pengajuan yang bukan NEEDS_REVISION dialihkan ke detail', function () {
    [$owner, , $submission] = threadAuthorFixture(UnderReview::class);

    $this->actingAs($owner)
        ->get(route('user.submissions.revision', $submission))
        ->assertRedirect(route('user.submissions.show', $submission));
});

it('[SM-ACCESS-03] peneliti lain ditolak di halaman revisi', function () {
    [, , $submission] = threadAuthorFixture(UnderReview::class);

    // BR-SM-07 diperiksa lebih dulu: orang luar tidak dialihkan ke detail, ia ditolak.
    $this->actingAs(User::factory()->create())
        ->get(route('user.submissions.revision', $submission))
        ->assertForbidden();
});
