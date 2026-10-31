<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\Wizard\MemberCandidateData;
use App\Models\FormSubmission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Calon anggota langkah 4: dosen ber-`submissions.create` yang belum berperan di pengajuan ini.
 * Reviewer pengajuan ini tetap ditampilkan tapi diberi alasan, supaya peneliti tahu kenapa ia
 * tidak bisa ditambahkan (BR-SM-08) alih-alih mengira orangnya tidak terdaftar.
 *
 * @method static list<MemberCandidateData> run(FormSubmission $submission, string $search)
 */
class ResearchMemberCandidates implements ChangesNoState
{
    use AsAction;

    private const MIN_SEARCH = 2;

    private const LIMIT = 10;

    /** @return list<MemberCandidateData> */
    public function handle(FormSubmission $submission, string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < self::MIN_SEARCH) {
            return [];
        }

        $reviewerIds = $submission->submissionReviewers()->with('reviewer:id,user_id')->get()
            ->map(fn (SubmissionReviewer $assignment): int => $assignment->reviewer->user_id)
            ->all();
        $candidates = User::permission('submissions.create')
            // Ketua dan anggota aktif sudah berperan; anggota nonaktif boleh ditawarkan lagi.
            ->whereKeyNot($submission->submitted_by)
            ->whereDoesntHave('userProfile.researchMembers', fn (Builder $m) => $m
                ->where('form_submission_id', $submission->id)->where('status', 'active'))
            ->where(fn (Builder $q) => $q->whereLike('name', "%{$search}%")
                ->orWhereHas('userProfile', fn (Builder $p) => $p->whereLike('nidn', "%{$search}%")))
            ->with('userProfile:id,user_id,nidn')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return array_values($candidates->map(fn (User $user): MemberCandidateData => new MemberCandidateData(
            id: $user->id,
            name: $user->name,
            nidn: $user->userProfile?->nidn,
            blockedReason: in_array($user->id, $reviewerIds, true)
                ? "{$user->name} sedang ditugaskan sebagai reviewer pengajuan ini, jadi ia tidak dapat ditambahkan sebagai anggota."
                : null,
        ))->all());
    }
}
