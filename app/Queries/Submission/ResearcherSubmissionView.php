<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\ResearchOutput;
use App\Models\ReviewComment;
use App\Models\SubmissionReviewer;
use App\Models\SubmissionTargetOutput;
use App\Models\User;
use App\Queries\Review\AssignedReviewerCards;
use App\Queries\Review\ReviewerEvaluationChecklist;
use App\Queries\Review\ReviewThreads;
use App\Queries\Review\VisibleReviewerScores;
use App\States\Submission\SubmissionStatus;
use App\Support\PhaseAccessGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi layar detail pengajuan bagi pemilik, anggota, dan reviewer yang bertugas. Akses level
 * pengajuan (BR-SM-07) dijaga controller sebelum Query ini dipanggil.
 *
 * @phpstan-import-type ChecklistItem from ReviewerEvaluationChecklist
 * @phpstan-import-type ThreadStats from ReviewThreads
 * @phpstan-import-type ReviewerCard from AssignedReviewerCards
 * @phpstan-import-type Score from VisibleReviewerScores
 *
 * @phpstan-type PhaseAccess array{is_open: bool, deadline: string|null, form_phase_detail_id: int|null, override: array{id: int, status: string, expires_at: string|null, reason: string|null}|null}
 * @phpstan-type Evaluation array{canReview: bool, canCreateThread: bool, reviewerFormAssignments: list<ChecklistItem>, hasPendingEvaluations: bool, pendingEvaluationsCount: int}
 * @phpstan-type View array{submission: FormSubmission, responses: array<int, string|null>, reviewComments: Collection<int, ReviewComment>, reviewStats: ThreadStats, userRole: string, isOwnSubmission: bool, abilities: SubmissionAbilitiesData, printableDocuments: list<array{value: string, label: string}>, canManageContent: bool, targetOutputs: Collection<int, SubmissionTargetOutput>, allowedOutputTypes: list<array{code: string, name: string}>, researchMembers: list<array{id: int, name: string|null, role: string}>, maxMembers: int|null, memberCandidates: Collection<int, User>, assignedReviewers: list<ReviewerCard>, hasReviewEvaluationForms: bool, submissionStatus: SubmissionStatus|null, phaseAccess: PhaseAccess, canGrantOverride: bool, reviewerScores: array{visible: bool, reason: string|null, scores: list<Score>}, childPhases: list<array{id: int, title: string}>, researchOutputs: Collection<int, ResearchOutput>, outputTypes: list<array{code: string, name: string, requires_files: bool, required_fields: list<string>}>, canManageOutputs: bool, canReview: bool, canCreateThread: bool, reviewerFormAssignments: list<ChecklistItem>, hasPendingEvaluations: bool, pendingEvaluationsCount: int}
 *
 * @method static View run(User $user, FormSubmission $submission)
 */
class ResearcherSubmissionView implements ChangesNoState
{
    use AsAction;

    /** @return View */
    public function handle(User $user, FormSubmission $submission): array
    {
        $responses = SubmissionAnswers::run($submission);
        $threads = ReviewThreads::run($submission);
        $submission->setRelation('reviewSummaries', $threads['summaries']);

        ['role' => $role, 'assignment' => $assignment] = SubmissionViewerRole::run($submission, $user);
        $detail = $submission->getFormPhaseDetail();

        return [
            'submission' => $submission,
            'responses' => $responses,
            'reviewComments' => $threads['comments'],
            'reviewStats' => $threads['stats'],
            'userRole' => $role,
            'isOwnSubmission' => $submission->submitted_by === $user->id,
            ...$this->permissions($user, $submission, $detail),
            'targetOutputs' => $submission->targetOutputs()->get(['id', 'output_type']),
            'allowedOutputTypes' => TargetOutputTypeChoices::run($submission),
            ...$this->membership($submission),
            'assignedReviewers' => AssignedReviewerCards::run($submission),
            'hasReviewEvaluationForms' => $detail?->hasReviewEvaluationForms() ?? false,
            'submissionStatus' => $submission->status,
            // BR-REV-11/12 — dijaga di payload, bukan di CSS: skor yang sampai ke browser tak bisa
            // disembunyikan tampilan dengan aman. Alasannya ikut supaya layar bisa menjelaskan.
            'reviewerScores' => VisibleReviewerScores::run($user, $submission),
            'childPhases' => AvailableChildPhases::run($submission),
            'researchOutputs' => $this->researchOutputs($submission),
            'outputTypes' => RealisedOutputTypeChoices::run($submission),
            ...$this->evaluation($role === 'reviewer' ? $assignment : null, $detail),
        ];
    }

    /** @return array{abilities: SubmissionAbilitiesData, printableDocuments: list<array{value: string, label: string}>, canManageContent: bool, phaseAccess: PhaseAccess, canGrantOverride: bool, canManageOutputs: bool} */
    private function permissions(User $user, FormSubmission $submission, ?FormPhaseDetail $detail): array
    {
        return [
            // Jawaban Policy atas empat tindakan utama, supaya layar tidak menyusun ulang aturannya.
            // Tebakan lama ("pemiliknya dan belum disubmit") melewatkan arsip periode (BR-SM-10)
            // dan status terminal (BR-SM-05).
            'abilities' => SubmissionAbilitiesData::forUser($user, $submission),
            'printableDocuments' => PrintableDocumentChoices::run($user, $submission),
            // BR-SM-15 — Policy yang memutuskan siapa boleh mengubah target, bukan sekadar
            // "pemiliknya": status terminal ikut menutupnya.
            'canManageContent' => $user->can('manageTargetOutput', $submission),
            // BR-FE-08/15/16 — fase lewat batas tetap terbaca, hanya terkunci; peneliti melihat
            // *status* perpanjangan tanpa alasannya. Siapa boleh membaca alasan diputus Policy.
            'phaseAccess' => PhaseAccessGate::presentFor($submission, $detail, $user->can('viewOverrideReason', $submission)),
            'canGrantOverride' => $user->can('grantOverride', $submission),
            'canManageOutputs' => $user->can('manageResearchOutput', $submission),
        ];
    }

    /** @return array{researchMembers: list<array{id: int, name: string|null, role: string}>, maxMembers: int|null, memberCandidates: Collection<int, User>} */
    private function membership(FormSubmission $submission): array
    {
        return [
            'researchMembers' => array_values($submission->researchMembers()
                ->where('status', 'active')
                ->with('userProfile.user')
                ->get()
                ->map(fn (ResearchMember $m): array => ['id' => $m->id, 'name' => $m->userProfile->user?->name, 'role' => $m->role])
                ->all()),
            'maxMembers' => $submission->scheme?->max_members,
            // Calon anggota: sudah punya profil dan belum terlibat di pengajuan ini. Penyaring
            // konflik kepentingan yang sebenarnya tetap di ConflictOfInterestGuard — ini hanya
            // supaya daftar yang ditawarkan tidak penuh nama yang pasti ditolak.
            'memberCandidates' => User::query()
                ->whereHas('userProfile')
                ->where('id', '!=', $submission->submitted_by)
                ->whereDoesntHave('userProfile.researchMembers', fn (Builder $q) => $q->where('form_submission_id', $submission->id))
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email']),
        ];
    }

    /** @return Collection<int, ResearchOutput> BR-RO-01..09 — luaran terealisasi */
    private function researchOutputs(FormSubmission $submission): Collection
    {
        $outputs = $submission->researchOutputs()
            ->select(['id', 'output_type', 'metadata'])
            ->withCount('files as file_count')
            ->latest('id')
            ->get();

        // Layar membaca `metadata.title`; null akan memecahkannya.
        $outputs->each(function (ResearchOutput $output): void {
            $output->metadata ??= [];
        });

        return $outputs;
    }

    /** @return Evaluation */
    private function evaluation(?SubmissionReviewer $assignment, ?FormPhaseDetail $detail): array
    {
        if ($assignment === null) {
            return [
                'canReview' => false,
                'canCreateThread' => false,
                'reviewerFormAssignments' => [],
                'hasPendingEvaluations' => false,
                'pendingEvaluationsCount' => 0,
            ];
        }

        $checklist = ReviewerEvaluationChecklist::run($assignment, $detail);
        // BR-FE-06 — layar memakai definisi guard ulir (ReviewSummaryGuard), bukan hitungan form
        // sendiri; hitungan sendiri menawarkan tombol yang ditolak dan menahan yang diizinkan.
        $settled = $assignment->hasSettledEvaluation();
        $unsubmitted = array_filter($checklist, fn (array $item): bool => $item['is_required']
            && ($item['review_form_response']['status'] ?? null) !== 'submitted');

        return [
            'canReview' => true,
            'canCreateThread' => $settled,
            'reviewerFormAssignments' => $checklist,
            'hasPendingEvaluations' => !$settled,
            'pendingEvaluationsCount' => $settled ? 0 : count($unsubmitted),
        ];
    }
}
