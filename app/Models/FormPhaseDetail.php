<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
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
 * @property int $review_evaluation_forms_count hanya setelah withCount(EVALUATION_FORM_COUNTS)
 * @property int $required_review_evaluation_forms_count
 * @property-read FormPhase|null $formPhase
 * @property-read SubmissionDate $submissionDate
 * @property-read FormAccessControl|null $formAccessControl
 */
class FormPhaseDetail extends Model
{
    use HasFactory;

    /** Untuk withCount: satu subquery per hitungan, bukan dua COUNT per detail saat dijumlah per tahap. */
    public const EVALUATION_FORM_COUNTS = [
        'activeReviewEvaluationForms as review_evaluation_forms_count',
        'requiredReviewEvaluationForms as required_review_evaluation_forms_count',
    ];

    /**
     * BR-FE-20 — detail yang dirujuk pengajuan tidak bisa dihapus.
     */
    protected static function booted(): void
    {
        static::deleting(function (FormPhaseDetail $detail): void {
            ReferenceGuard::assertUnreferenced('Detail tahap', [
                'pengajuan' => FormSubmission::query()
                    ->where('form_phase_detail_id', $detail->id)
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
    ];

    protected $casts = [
        'needs_review' => 'boolean',
    ];

    /**
     * Get the form phase that owns the detail.
     *
     * @return BelongsTo<FormPhase, $this>
     */
    public function formPhase(): BelongsTo
    {
        return $this->belongsTo(FormPhase::class);
    }

    /**
     * Get the form access control for the detail.
     *
     * @return BelongsTo<FormAccessControl, $this>
     */
    public function formAccessControl(): BelongsTo
    {
        return $this->belongsTo(FormAccessControl::class);
    }

    /**
     * Get the submission date for the detail.
     *
     * @return BelongsTo<SubmissionDate, $this>
     */
    public function submissionDate(): BelongsTo
    {
        return $this->belongsTo(SubmissionDate::class);
    }

    /**
     * Get the phase type for the detail.
     *
     * @return BelongsTo<PhaseType, $this>
     */
    public function phaseType(): BelongsTo
    {
        return $this->belongsTo(PhaseType::class);
    }

    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function reviewEvaluationForms(): HasMany
    {
        return $this->hasMany(ReviewEvaluationForm::class);
    }

    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function activeReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->active()->ordered();
    }

    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function requiredReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->required()->active()->ordered();
    }

    public function hasReviewEvaluationForms(): bool
    {
        return $this->activeReviewEvaluationForms()->exists();
    }

    /**
     * BR-FE-17 — jendela kerja detail ini sendiri. Periodenya dibaca dari `SubmissionDate`
     * milik detail (ADR 0014), bukan `formPhase->submissionPeriod`: `form_phases` tidak
     * punya kolom `submission_period_id`, jadi relasi itu selalu null.
     */
    public function isWithinDeadline(): bool
    {
        $date = $this->submissionDate;
        $period = $date->submissionPeriod;

        // Force close mengalahkan tanggal per detail — sumbu yang berbeda.
        if ($period !== null && $period->is_force_closed) {
            return false;
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
