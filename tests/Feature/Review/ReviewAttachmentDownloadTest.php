<?php

// F3 — unduh lampiran review hanya bagi yang boleh melihat pengajuannya (BR-SM-07).

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\ReviewCommentAttachment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\ReviewSummaryAttachment;
use App\Models\User;
use App\States\Submission\UnderReview;
use Illuminate\Support\Facades\Storage;

/** @return array{0: User, 1: FormSubmission, 2: ReviewSummary} */
function attachmentFixture(): array
{
    Storage::fake('public');

    $detail = FormPhaseDetail::factory()->create(['needs_review' => true]);
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'form_phase_detail_id' => $detail->id,
        'submitted_by' => $owner->id,
        'status' => UnderReview::class,
    ]);

    $reviewer = Reviewer::factory()->create([
        'user_id' => User::factory()->create()->id,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);

    $summary = ReviewSummary::create([
        'form_submission_id' => $submission->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'open',
        'summary_notes' => 'catatan',
    ]);

    return [$owner, $submission, $summary];
}

function download(User $user, string $path)
{
    return test()->actingAs($user)->get(route('review-attachments.download', ['path' => $path]));
}

it('[BR-SM-07] pengaju mengunduh lampiran ulir pengajuannya', function () {
    [$owner, $submission, $summary] = attachmentFixture();
    $path = 'review-attachments/'.$submission->id.'/catatan.pdf';
    Storage::disk('public')->put($path, 'isi');
    ReviewSummaryAttachment::create(['review_summary_id' => $summary->id, 'file_path' => $path]);

    download($owner, $path)->assertOk();
});

it('[BR-SM-07] pengguna tanpa akses ke pengajuan ditolak', function () {
    [, $submission, $summary] = attachmentFixture();
    $path = 'review-attachments/'.$submission->id.'/catatan.pdf';
    Storage::disk('public')->put($path, 'isi');
    ReviewSummaryAttachment::create(['review_summary_id' => $summary->id, 'file_path' => $path]);

    download(User::factory()->create(), $path)->assertForbidden();
});

it('[BR-SM-07] berkas yang bukan lampiran review tidak bisa diunduh', function () {
    [$owner] = attachmentFixture();
    Storage::disk('public')->put('avatars/x.png', 'isi');

    download($owner, 'avatars/x.png')->assertNotFound();
});

it('[BR-SM-07] lampiran komentar mengikuti aturan yang sama', function () {
    [$owner, $submission, $summary] = attachmentFixture();
    $comment = ReviewComment::create([
        'review_summary_id' => $summary->id,
        'user_id' => $owner->id,
        'comment_text' => 'balasan',
    ]);
    $path = 'review-attachments/'.$submission->id.'/komentar.pdf';
    Storage::disk('public')->put($path, 'isi');
    ReviewCommentAttachment::create(['review_comment_id' => $comment->id, 'file_path' => $path]);

    download($owner, $path)->assertOk();
    download(User::factory()->create(), $path)->assertForbidden();
});
