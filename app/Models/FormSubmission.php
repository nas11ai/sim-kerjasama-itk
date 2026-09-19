<?php

namespace App\Models;

use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\Withdrawn;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int $form_id
 * @property int $submitted_by
 * @property int|null $parent_submission_id
 * @property int|null $form_phase_detail_id
 * @property int|null $submission_period_id
 * @property string|null $rejection_reason
 * @property int|null $scheme_id
 * @property int|null $trl_id
 * @property int $last_completed_step
 * @property bool $is_submitted
 * @property-read bool $is_archived
 * @property SubmissionStatus|null $status
 * @property-read Form $form
 * @property-read User $submittedBy
 * @property-read Scheme|null $scheme
 * @property-read FormPhaseDetail|null $formPhaseDetail
 * @property-read SubmissionPeriod|null $submissionPeriod
 * @property-read TechnologyReadinessLevel|null $trl
 * @property-read Collection<int, FormFieldResponse> $formFieldResponses
 * @property-read Collection<int, SubmissionReviewer> $submissionReviewers
 * @property-read Collection<int, ReviewSummary> $reviewSummaries
 * @property-read Collection<int, BudgetLineItem> $budgetLineItems
 * @property-read Collection<int, ResearchMember> $researchMembers
 * @property-read Collection<int, SubmissionTargetOutput> $targetOutputs
 */
class FormSubmission extends Model
{
    use HasFactory, HasStates;

    protected $fillable = [
        'form_id',
        'form_phase_detail_id',
        'submission_period_id',
        'rejection_reason',
        'scheme_id',
        'trl_id',
        'last_completed_step',
        'is_submitted',
        'status',
        'submitted_by',
        'parent_submission_id',
    ];

    protected $appends = ['is_archived'];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'is_submitted' => 'boolean',
        'last_completed_step' => 'integer',
    ];

    /** @return BelongsTo<FormSubmission, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'parent_submission_id');
    }

    /** @return HasMany<FormSubmission, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'parent_submission_id');
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<FormFieldResponse, $this>
     */
    public function formFieldResponses(): HasMany
    {
        return $this->hasMany(FormFieldResponse::class);
    }

    /** @return BelongsTo<Scheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    /** @return BelongsTo<FormPhaseDetail, $this> */
    public function formPhaseDetail(): BelongsTo
    {
        return $this->belongsTo(FormPhaseDetail::class);
    }

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<TechnologyReadinessLevel, $this> */
    public function trl(): BelongsTo
    {
        return $this->belongsTo(TechnologyReadinessLevel::class, 'trl_id');
    }

    /** @return HasMany<SubmissionReviewer, $this> */
    public function submissionReviewers(): HasMany
    {
        return $this->hasMany(SubmissionReviewer::class);
    }

    /** @return HasMany<ReviewSummary, $this> */
    public function reviewSummaries(): HasMany
    {
        return $this->hasMany(ReviewSummary::class);
    }

    /** @return HasMany<BudgetLineItem, $this> */
    public function budgetLineItems(): HasMany
    {
        return $this->hasMany(BudgetLineItem::class);
    }

    /** @return HasMany<SubmissionTargetOutput, $this> */
    public function targetOutputs(): HasMany
    {
        return $this->hasMany(SubmissionTargetOutput::class);
    }

    /**
     * BR-SM-21 — mitra eksternal yang dijanjikan saat mengajukan.
     *
     * @return HasMany<Partner, $this>
     */
    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    /**
     * BR-SM-20 — anggota mahasiswa; data, bukan akun.
     *
     * @return HasMany<StudentMember, $this>
     */
    public function studentMembers(): HasMany
    {
        return $this->hasMany(StudentMember::class);
    }

    /** @return HasMany<ResearchMember, $this> */
    public function researchMembers(): HasMany
    {
        return $this->hasMany(ResearchMember::class);
    }

    /**
     * BR-SM-07 — akses level submission, lapis terpisah dari FormAccessControl.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->can('submissions.view-all')) {
            return true;
        }

        if ($this->submitted_by === $user->id) {
            return true;
        }

        if ($this->hasActiveResearchMember($user)) {
            return true;
        }

        return $this->hasAssignedReviewer($user);
    }

    private function hasActiveResearchMember(User $user): bool
    {
        return $this->researchMembers()
            ->where('status', 'active')
            ->whereHas('userProfile', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    private function hasAssignedReviewer(User $user): bool
    {
        return $this->submissionReviewers()
            ->where('status', 'active')
            ->whereHas('reviewer', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    public function reviewComments()
    {
        return $this->hasMany(ReviewComment::class, 'review_summary_id', 'id')
            ->join('review_summaries', 'review_comments.review_summary_id', '=', 'review_summaries.id')
            ->where('review_summaries.form_submission_id', $this->id);
    }

    public function allReviewComments()
    {
        return $this->hasManyThrough(
            ReviewComment::class,
            ReviewSummary::class,
            'form_submission_id',
            'review_summary_id',
            'id',
            'id'
        );
    }

    // NEW: Get all reviewer form assignments through submission reviewers
    public function reviewerFormAssignments()
    {
        return $this->hasManyThrough(
            ReviewerFormAssignment::class,
            SubmissionReviewer::class,
            'form_submission_id',
            'submission_reviewer_id',
            'id',
            'id'
        );
    }

    // NEW: Get all review form responses
    public function reviewFormResponses()
    {
        return $this->hasManyThrough(
            ReviewFormResponse::class,
            ReviewerFormAssignment::class,
            'submission_reviewer_id',
            'reviewer_form_assignment_id',
            'id',
            'id'
        )->whereIn(
            'reviewer_form_assignments.submission_reviewer_id',
            $this->submissionReviewers()->pluck('id')
        );
    }

    // NEW: Get submitted review form responses
    public function submittedReviewFormResponses()
    {
        return $this->reviewFormResponses()->where('review_form_responses.status', 'submitted');
    }

    // NEW: Check if all reviewers have completed their evaluations
    public function allReviewersCompletedEvaluations(): bool
    {
        $reviewersWithPendingEvaluations = $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->count();

        return $reviewersWithPendingEvaluations === 0;
    }

    // NEW: Check if any reviewer has pending evaluations
    public function hasPendingEvaluations(): bool
    {
        return $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->exists();
    }

    // NEW: Get reviewers with completed evaluations
    public function reviewersWithCompletedEvaluations()
    {
        return $this->submissionReviewers()
            ->where('evaluation_status', 'completed')
            ->with(['reviewer.user', 'reviewer']);
    }

    // NEW: Get reviewers with pending evaluations
    public function reviewersWithPendingEvaluations()
    {
        return $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->with(['reviewer.user', 'reviewer']);
    }

    // NEW: Get evaluation completion statistics
    public function getEvaluationStats(): array
    {
        $totalReviewers = $this->submissionReviewers()->count();
        $completedEvaluations = $this->submissionReviewers()
            ->where('evaluation_status', 'completed')
            ->count();
        $pendingEvaluations = $this->submissionReviewers()
            ->where('evaluation_status', 'pending')
            ->count();
        $noEvaluationRequired = $this->submissionReviewers()
            ->where('evaluation_status', 'not_required')
            ->count();

        $completionPercentage = $totalReviewers > 0
            ? round((($completedEvaluations + $noEvaluationRequired) / $totalReviewers) * 100)
            : 100;

        return [
            'total_reviewers' => $totalReviewers,
            'completed_evaluations' => $completedEvaluations,
            'pending_evaluations' => $pendingEvaluations,
            'no_evaluation_required' => $noEvaluationRequired,
            'completion_percentage' => $completionPercentage,
            'all_completed' => $pendingEvaluations === 0,
        ];
    }

    // NEW: Auto-assign evaluation forms to new reviewers
    public function autoAssignEvaluationForms(): void
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        if (!$formPhaseDetail || !$formPhaseDetail->hasReviewEvaluationForms()) {
            return;
        }

        // Get reviewers without any form assignments
        $reviewersNeedingAssignment = $this->submissionReviewers()
            ->whereDoesntHave('reviewerFormAssignments')
            ->get();

        foreach ($reviewersNeedingAssignment as $submissionReviewer) {
            /** @var SubmissionReviewer $submissionReviewer */
            // Assign all required forms by default
            /** @var Collection<int, ReviewEvaluationForm> $requiredForms */
            $requiredForms = $formPhaseDetail->requiredReviewEvaluationForms()->get();

            foreach ($requiredForms as $form) {
                $submissionReviewer->assignForm($form->id, true, $this->getEvaluationDueDate());
            }
        }
    }

    /**
     * BR-RO-01 — luaran yang terwujud dari pengajuan ini.
     *
     * @return HasMany<ResearchOutput, $this>
     */
    public function researchOutputs(): HasMany
    {
        return $this->hasMany(ResearchOutput::class);
    }

    /**
     * BR-RO-02 — jenis pengajuan (Research / CommunityService).
     *
     * Diturunkan lewat skema, bukan kolom tersendiri: skema yang menentukan
     * jenis kegiatan, dan menyalinnya ke sini akan menciptakan sumber kedua
     * yang bisa menyimpang. Pengajuan tanpa skema tidak punya jenis — dan
     * memang tidak perlu, karena pembatasan tipe luaran cuma berlaku untuk
     * tipe yang menyatakan jenisnya.
     */
    public function submissionType(): ?SubmissionType
    {
        return $this->scheme?->submissionType;
    }

    /**
     * **Active Submission** — status-nya bukan REJECTED dan bukan WITHDRAWN.
     *
     * Predikat yang sama dipakai index unik BR-SM-01 (ADR 0005), penjagaan
     * deaktivasi user (BR-IAM-05), dan penonaktifan skema (BR-SCH-01). Ia
     * tinggal di satu tempat justru karena dipakai bertiga: definisi yang
     * disalin akan menyimpang, dan menyimpangnya baru ketahuan saat salah satu
     * gerbang melepaskan sesuatu yang seharusnya ditahan.
     *
     * `whereNotState`, bukan `whereNotIn`: kolomnya menyimpan morph name
     * ("rejected"), bukan nama kelas.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeActive($query)
    {
        return $query->whereNotState('status', [Rejected::class, Withdrawn::class]);
    }

    /**
     * BR-SM-13 — draft terarsip kalau periodenya sudah tertutup.
     *
     * Predikat "periode tertutup"-nya sama persis dengan yang menggerbangi
     * pembuatan pengajuan (BR-FE-01), jadi ia tinggal satu tempat saja di
     * SubmissionPeriodGate. Dua salinan pasti menyimpang: yang satu akan ingat
     * `is_force_closed`, yang lain tidak.
     */
    public function resolveIsArchived(): bool
    {
        if ($this->is_submitted) {
            return false;
        }

        return SubmissionPeriodGate::isClosed($this->submissionPeriod);
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->resolveIsArchived();
    }

    public function getFormPhase(): ?FormPhase
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        return $formPhaseDetail ? $formPhaseDetail->formPhase : null;
    }

    // NEW: Get form phase for this submission
    public function getFormPhaseDetail(): ?FormPhaseDetail
    {
        return $this->formPhaseDetail;
    }

    // NEW: Get evaluation due date based on submission period
    protected function getEvaluationDueDate(): ?\DateTime
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        if ($formPhaseDetail === null || $formPhaseDetail->formPhase === null) {
            return null;
        }

        // Period diturunkan lewat FK eksplisit submission_period_id, bukan
        // ditebak lewat submission_period_phases — pivot itu tanpa unique
        // constraint, satu FormPhase boleh dimiliki banyak period (ADR 0004).
        $submissionPeriod = $this->submissionPeriod;

        if ($submissionPeriod === null) {
            return null;
        }

        // Get the latest submission date as due date
        /** @var SubmissionDate|null $latestDate */
        $latestDate = $submissionPeriod->submissionDates()
            ->orderBy('datetime', 'desc')
            ->first();

        return $latestDate ? $latestDate->datetime : null;
    }

    // NEW: Check if evaluation phase is complete and can proceed to discussions
    public function canProceedToDiscussions(): bool
    {
        return $this->allReviewersCompletedEvaluations();
    }

    /**
     * BR-MON-02 — apakah tahap ini boleh dilanjutkan ke detail berikutnya.
     *
     * Turunan keadaan, bukan kolom: tidak ada aktor yang menyetelnya, sejajar
     * `isArchived()` (register #13). Bernilai true kalau baris ini **sudah
     * disubmit** dan, bila detailnya `needs_review`, evaluasinya selesai tanpa
     * ReviewSummary yang masih `open`.
     *
     * Versi sebelumnya memeriksa `status instanceof Approved` beserta
     * persetujuan seluruh reviewer — itu menjawab pertanyaan yang berbeda
     * ("apakah pengajuan ini disetujui"), dan membuat setiap laporan monev
     * terkunci selamanya karena laporan memang tidak pernah berstatus APPROVED.
     */
    public function canProceed(): bool
    {
        if (!$this->is_submitted) {
            return false;
        }

        $detail = $this->formPhaseDetail;

        // Detail yang tidak menuntut evaluasi selesai begitu laporannya masuk.
        // Menunggu evaluasi yang memang tidak ada akan mengunci siklus
        // berikutnya selamanya.
        if ($detail === null || !$detail->needs_review) {
            return true;
        }

        $adaEvaluasiTertunda = $this->submissionReviewers()
            ->where('status', 'active')
            ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION)
            ->exists();

        if ($adaEvaluasiTertunda) {
            return false;
        }

        // BR-FE-06 — ulir yang masih terbuka berarti masih ada yang diminta
        // diperbaiki.
        return !$this->reviewSummaries()->where('status', 'open')->exists();
    }

    public function needsRevision(): bool
    {
        return $this->status instanceof NeedsRevision;
    }

    // Updated: Check if discussions are allowed
    public function discussionsAllowed(): bool
    {
        // Discussions are allowed if:
        // 1. No evaluation forms required, OR
        // 2. All required evaluations are completed
        return !$this->hasPendingEvaluations();
    }

    // Existing helper methods
    public function allReviewersApproved()
    {
        $totalReviewers = $this->submissionReviewers()->count();
        $approvedReviewers = $this->reviewSummaries()
            ->where('status', 'resolved')
            ->distinct('reviewer_id')
            ->count('reviewer_id');

        return $totalReviewers > 0 && $totalReviewers === $approvedReviewers;
    }

    public function hasRevisionsRequested()
    {
        return $this->reviewSummaries()
            ->where('status', 'open')
            ->exists();
    }

    public function hasAnyReviews()
    {
        return $this->reviewSummaries()->exists();
    }

    // Updated: Enhanced status update considering evaluations

    public function getActiveReviewThreadsCount(): int
    {
        return $this->reviewSummaries()->where('status', 'open')->count();
    }

    public function getResolvedReviewThreadsCount(): int
    {
        return $this->reviewSummaries()->where('status', 'resolved')->count();
    }

    public function hasOpenReviewThreads(): bool
    {
        return $this->reviewSummaries()->where('status', 'open')->exists();
    }

    public function assignReviewer(int $reviewerId): ReviewSummary
    {
        /** @var ReviewSummary $summary */
        $summary = $this->reviewSummaries()->firstOrCreate(
            ['reviewer_id' => $reviewerId],
            ['status' => 'open']
        );

        return $summary;
    }

    public function removeReviewer(int $reviewerId): bool
    {
        return $this->reviewSummaries()
            ->where('reviewer_id', $reviewerId)
            ->delete() > 0;
    }

    // NEW: Get evaluation forms summary for this submission
    public function getEvaluationFormsSummary(): array
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        if (!$formPhaseDetail) {
            return [];
        }

        /** @var Collection<int, ReviewEvaluationForm> $evaluationForms */
        $evaluationForms = $formPhaseDetail->activeReviewEvaluationForms()->get();
        $summary = [];

        foreach ($evaluationForms as $form) {
            $assignedCount = $this->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->count();

            $completedCount = $this->reviewerFormAssignments()
                ->where('review_evaluation_form_id', $form->id)
                ->whereHas('reviewFormResponse', function ($query) {
                    $query->where('status', 'submitted');
                })
                ->count();

            $summary[] = [
                'form' => $form,
                'assigned_count' => $assignedCount,
                'completed_count' => $completedCount,
                'completion_percentage' => $assignedCount > 0
                    ? round(($completedCount / $assignedCount) * 100)
                    : 0,
            ];
        }

        return $summary;
    }

    // Check if form phase has any review evaluation forms
    public function hasReviewEvaluationForms(): bool
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        return $formPhaseDetail ? $formPhaseDetail->hasReviewEvaluationForms() : false;
    }

    // Check if review evaluation forms are required for this submission
    public function requiresReviewEvaluation(): bool
    {
        $formPhaseDetail = $this->getFormPhaseDetail();

        return $formPhaseDetail ? $formPhaseDetail->requiresEvaluationCompletion() : false;
    }

    // Check if a specific reviewer has completed their evaluation (or if evaluation not required)
    public function reviewerCanCreateThreads(int $reviewerId): bool
    {
        // If no evaluation forms required, reviewer can create threads immediately
        if (!$this->hasReviewEvaluationForms()) {
            return true;
        }

        // Check if reviewer is assigned
        $submissionReviewer = $this->submissionReviewers()
            ->where('reviewer_id', $reviewerId)
            ->first();

        if ($submissionReviewer === null) {
            return false;
        }

        /** @var SubmissionReviewer $submissionReviewer */
        return $submissionReviewer->canCreateDiscussionThreads();
    }

    // Check if a specific reviewer can participate in discussions
    public function reviewerCanParticipate(int $reviewerId): bool
    {
        // Same logic as canCreateThreads
        return $this->reviewerCanCreateThreads($reviewerId);
    }

    // Get evaluation requirements summary
    public function getEvaluationRequirements(): array
    {
        if (!$this->hasReviewEvaluationForms()) {
            return [
                'required' => false,
                'has_forms' => false,
                'message' => 'No evaluation forms required for this submission.',
            ];
        }

        $formPhaseDetail = $this->getFormPhaseDetail();
        if (!$formPhaseDetail) {
            return [
                'required' => false,
                'has_forms' => false,
                'message' => 'No form phase detail found.',
            ];
        }

        $formsCount = $formPhaseDetail->review_evaluation_forms_count;
        $requiredCount = $formPhaseDetail->required_review_evaluation_forms_count;

        return [
            'required' => true,
            'has_forms' => true,
            'total_forms' => $formsCount,
            'required_forms' => $requiredCount,
            'message' => 'This submission requires evaluation forms to be completed before creating review threads.',
        ];
    }
}
