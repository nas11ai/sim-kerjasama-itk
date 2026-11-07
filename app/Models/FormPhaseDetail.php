<?php

namespace App\Models;

use App\Enums\DeadlineType;
use App\Support\Lifecycle\ReferenceGuard;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_phase_id
 * @property int $form_access_control_id
 * @property int $phase_type_id
 * @property int $order
 * @property bool $needs_review
 * @property int $submission_date_id
 * @property int|null $evaluation_date_id
 * @property int|null $revision_date_id
 * @property int $submission_count hanya setelah PhaseEdit
 * @property int $assignment_count hanya setelah PhaseEdit
 * @property int $evaluation_form_count hanya setelah PhaseEdit
 * @property-read FormPhase|null $formPhase
 * @property-read SubmissionDate $submissionDate
 * @property-read SubmissionDate|null $evaluationDate
 * @property-read SubmissionDate|null $revisionDate
 * @property-read FormAccessControl|null $formAccessControl
 * @property-read PhaseType|null $phaseType
 */
class FormPhaseDetail extends Model
{
    use HasFactory;

    /**
     * BR-FE-20 — detail yang dirujuk pengajuan tidak bisa dihapus. BR-FE-22 — begitu pula detail
     * yang form evaluasinya pernah ditugaskan: tanpa penjaga ini form evaluasinya ikut ter-cascade.
     */
    protected static function booted(): void
    {
        static::deleting(function (FormPhaseDetail $detail): void {
            ReferenceGuard::assertUnreferenced('Detail tahap', [
                'pengajuan' => FormSubmission::query()
                    ->where('form_phase_detail_id', $detail->id)
                    ->count(),
                'penugasan form evaluasi' => ReviewerFormAssignment::query()
                    ->whereIn('review_evaluation_form_id', $detail->reviewEvaluationForms()->select('id'))
                    ->count(),
            ]);
        });
    }

    protected $fillable = [
        'form_phase_id',
        'form_access_control_id',
        'phase_type_id',
        'order',
        'needs_review',
        'submission_date_id',
        'evaluation_date_id',
        'revision_date_id',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
    ];

    /** @return BelongsTo<FormPhase, $this> */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    /** @return BelongsTo<FormAccessControl, $this> */
    public function formAccessControl(): BelongsTo
    {
        return $this->belongsTo(FormAccessControl::class);
    }

    /** @return BelongsTo<SubmissionDate, $this> */
    public function submissionDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class);
    }

    /**
     * BR-FE-26 — Batas Evaluasi reviewer form evaluasi di detail ini; wajib bila `needs_review`.
     *
     * @return BelongsTo<SubmissionDate, $this>
     */
    public function evaluationDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class, 'evaluation_date_id');
    }

    /**
     * BR-FE-26 — Batas Revisi pengisi saat NEEDS_REVISION; tanpanya revisi memakai Batas Submit.
     *
     * @return BelongsTo<SubmissionDate, $this>
     */
    public function revisionDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class, 'revision_date_id');
    }

    /** @return BelongsTo<PhaseType, $this> */
    public function phaseType(): BelongsTo
    {
        return $this->belongsTo(PhaseType::class);
    }

    /** @return HasMany<ReviewEvaluationForm, $this> */
    public function reviewEvaluationForms(): HasMany
    {
        return $this->hasMany(ReviewEvaluationForm::class);
    }

    /** @return HasMany<ReviewEvaluationForm, $this> */
    public function activeReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->active()->ordered();
    }

    /** @return HasMany<ReviewEvaluationForm, $this> */
    public function requiredReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->required()->active()->ordered();
    }

    public function hasReviewEvaluationForms(): bool
    {
        return $this->activeReviewEvaluationForms()->exists();
    }

    /**
     * BR-FE-26 — tenggat detail ini untuk satu jenis. Batas Revisi yang kosong kembali ke
     * Batas Submit (perilaku sebelum E1b); Batas Evaluasi yang kosong berarti tak bertenggat.
     */
    public function deadlineOf(DeadlineType $type): ?SubmissionDate
    {
        return match ($type) {
            // W139 — detail Upload Luaran memegang Batas Akhir di kolom Batas Submit-nya.
            DeadlineType::Submit, DeadlineType::Output => $this->submissionDate,
            DeadlineType::Evaluation => $this->evaluationDate,
            DeadlineType::Revision => $this->revisionDate ?? $this->submissionDate,
        };
    }

    /** W139 — detail Upload Luaran (DDD monev Detail 7): Batas Submit-nya berjenis `output`. */
    public function isOutputUpload(): bool
    {
        return $this->submissionDate->type === DeadlineType::Output;
    }

    /**
     * BR-FE-17 — jendela kerja satu aktor detail ini. Periodenya dibaca dari `SubmissionDate`
     * milik detail (ADR 0014), bukan `formPhase->submissionPeriod`: `form_phases` tidak
     * punya kolom `submission_period_id`, jadi relasi itu selalu null.
     */
    public function isWithinDeadline(DeadlineType $type = DeadlineType::Submit): bool
    {
        // BR-SCH-07 — force close mengalahkan tanggal per detail — sumbu yang berbeda.
        if (SubmissionPeriodGate::isForceClosed($this)) {
            return false;
        }

        $date = $this->deadlineOf($type);

        return $date === null || now()->lte($date->datetime);
    }

    /**
     * BR-FE-21 — detail yang digerbangi salah satu tanggal ini, lewat kolom tenggat mana pun.
     *
     * @param  Builder<FormPhaseDetail>  $query
     * @param  Builder<SubmissionDate>  $dates  subquery `select id`
     * @return Builder<FormPhaseDetail>
     */
    public function scopeDeadlinedBy(Builder $query, Builder $dates): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereIn('submission_date_id', $dates)
            ->orWhereIn('evaluation_date_id', $dates)
            ->orWhereIn('revision_date_id', $dates));
    }

    /**
     * @param  Builder<FormPhaseDetail>  $query
     * @return Builder<FormPhaseDetail>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }
}
