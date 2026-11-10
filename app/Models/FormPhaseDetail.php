<?php

namespace App\Models;

use App\Enums\DeadlineType;
use App\Models\Concerns\HasStepDateReferences;
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
 * @property DeadlineType $deadline_type BR-FE-07 — `submit` atau `output`
 * @property int|null $deadline_cycle BR-FE-07 — null = pengajuan/luaran, n = laporan Monev n
 * @property int|null $submission_date_id
 * @property int|null $evaluation_date_id
 * @property int|null $revision_date_id
 * @property int $submission_count hanya setelah PhaseEdit
 * @property int $assignment_count hanya setelah PhaseEdit
 * @property int $evaluation_form_count hanya setelah PhaseEdit
 * @property-read FormPhase|null $formPhase
 * @property-read SubmissionDate|null $submissionDate
 * @property-read SubmissionDate|null $evaluationDate
 * @property-read SubmissionDate|null $revisionDate
 * @property-read FormAccessControl|null $formAccessControl
 * @property-read PhaseType|null $phaseType
 */
class FormPhaseDetail extends Model
{
    use HasFactory;
    use HasStepDateReferences;

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
        'deadline_type',
        'deadline_cycle',
        'submission_date_id',
        'evaluation_date_id',
        'revision_date_id',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'deadline_type' => DeadlineType::class,
        'deadline_cycle' => 'integer',
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
     * BR-MON-01 — pintu pengajuan baru hanya langkah `submit` tanpa siklus (BR-FE-07). Laporan
     * monev (bersiklus) dan Upload Luaran (`output`) lahir dari pengajuan APPROVED.
     */
    public function opensSubmission(): bool
    {
        return $this->deadline_type === DeadlineType::Submit && $this->deadline_cycle === null;
    }

    /** BR-FE-25 — nama tanggal yang dibaca langkah ini di kalender periode, mis. "Batas Laporan Monev I". */
    public function deadlineLabel(): string
    {
        return $this->deadline_type->label($this->deadline_cycle);
    }

    /**
     * BR-FE-17, BR-FE-26 — jendela kerja satu aktor langkah ini di kalender `$period` (periode
     * pengajuan). Tanggal kerja yang belum diatur menutup; Batas Evaluasi yang belum diatur
     * berarti tak bertenggat.
     */
    public function isWithinDeadline(SubmissionPeriod $period, DeadlineType $type = DeadlineType::Submit): bool
    {
        // BR-SCH-07 — force close mengalahkan tanggal — sumbu yang berbeda.
        if (SubmissionPeriodGate::isForceClosed($period, $this)) {
            return false;
        }

        $date = $period->deadlineFor($this, $type);

        if ($date === null) {
            return $type === DeadlineType::Evaluation;
        }

        return now()->lte($date->datetime);
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
