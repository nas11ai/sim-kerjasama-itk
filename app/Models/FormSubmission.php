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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    /**
     * BR-REV-05/BR-REV-15 — yang ditampilkan dan dihitung hanyalah penugasan
     * yang masih berlaku. Yang `replaced` tetap tersimpan sebagai sejarah,
     * tapi ia bukan pekerjaan dan bukan anggota tim review hari ini.
     *
     * @return HasMany<SubmissionReviewer, $this>
     */
    public function activeSubmissionReviewers(): HasMany
    {
        return $this->submissionReviewers()->active();
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

    /** @return HasManyThrough<ReviewerFormAssignment, SubmissionReviewer, $this> */
    public function reviewerFormAssignments(): HasManyThrough
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
     * BR-RO-02 — jenis pengajuan (Research / CommunityService), diturunkan lewat
     * skema, bukan kolom: salinannya akan jadi sumber kedua yang bisa menyimpang.
     * Pengajuan tanpa skema memang tidak punya jenis.
     */
    public function submissionType(): ?SubmissionType
    {
        return $this->scheme?->submissionType;
    }

    /**
     * **Active Submission** — status bukan REJECTED/WITHDRAWN. Satu definisi untuk
     * BR-SM-01 (ADR 0005), BR-IAM-05, dan BR-SCH-01. `whereNotState`, bukan
     * `whereNotIn`: kolomnya menyimpan morph name ("rejected"), bukan nama kelas.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeActive($query)
    {
        return $query->whereNotState('status', [Rejected::class, Withdrawn::class]);
    }

    /**
     * BR-SM-13 — draft terarsip kalau periodenya sudah tertutup. Predikat
     * "periode tertutup" dipinjam dari SubmissionPeriodGate (BR-FE-01) supaya
     * hanya ada satu salinan yang ingat `is_force_closed`.
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

    /**
     * BR-MON-02 — tahap ini boleh dilanjutkan: sudah disubmit dan, bila detailnya
     * `needs_review`, evaluasinya selesai tanpa ReviewSummary `open` (register #13).
     * Bukan `status instanceof Approved`: laporan monev tidak pernah APPROVED.
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

        if ($this->submissionReviewers()->unsettledEvaluation()->exists()) {
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
}
