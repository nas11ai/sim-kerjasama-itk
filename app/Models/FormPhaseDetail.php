<?php

namespace App\Models;

use App\Support\Lifecycle\ReferenceGuard;
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
 * @property int $reviewEvaluationFormsCount
 * @property int $requiredReviewEvaluationFormsCount
 * @property-read FormPhase|null $formPhase
 * @property-read SubmissionDate $submissionDate
 * @property-read FormAccessControl|null $formAccessControl
 */
class FormPhaseDetail extends Model
{
    use HasFactory;

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

    protected $appends = [
        'review_evaluation_forms_count',
        'required_review_evaluation_forms_count',
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
     * Get the review evaluation forms for this form phase detail.
     *
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    // NEW: Review evaluation forms relationship
    public function reviewEvaluationForms(): HasMany
    {
        return $this->hasMany(ReviewEvaluationForm::class);
    }

    // NEW: Active review evaluation forms
    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function activeReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->active()->ordered();
    }

    // NEW: Required review evaluation forms
    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function requiredReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()->required()->active()->ordered();
    }

    // NEW: Optional review evaluation forms
    /**
     * @return HasMany<ReviewEvaluationForm, $this>
     */
    public function optionalReviewEvaluationForms(): HasMany
    {
        return $this->reviewEvaluationForms()
            ->where('is_required', false)
            ->active()
            ->ordered();
    }

    // NEW: Check if phase has review evaluation forms
    public function hasReviewEvaluationForms(): bool
    {
        return $this->activeReviewEvaluationForms()->exists();
    }

    // NEW: Get count of review evaluation forms
    public function getReviewEvaluationFormsCountAttribute(): int
    {
        return $this->activeReviewEvaluationForms()->count();
    }

    // NEW: Get count of required review evaluation forms
    public function getRequiredReviewEvaluationFormsCountAttribute(): int
    {
        return $this->requiredReviewEvaluationForms()->count();
    }

    // Check if any form phase details need review
    public function needsReview(): bool
    {
        return static::query()
            ->where('form_phase_id', $this->form_phase_id)
            ->where('needs_review', true)
            ->exists();
    }

    // NEW: Check if phase requires evaluation forms to be completed before discussion
    public function requiresEvaluationCompletion(): bool
    {
        return $this->hasReviewEvaluationForms() && $this->needsReview();
    }

    /**
     * Scope a query to order by the order column.
     */
    /**
     * BR-FE-17 — jendela kerja detail ini sendiri.
     *
     * Periodenya dibaca dari `SubmissionDate` milik detail ini, bukan dari
     * `formPhase->submissionPeriod`: tabel `form_phases` tidak punya kolom
     * `submission_period_id`, jadi relasi itu selalu null dan method ini
     * dulu selalu fatal. Tidak pernah ketahuan karena belum ada pemanggilnya.
     *
     * Sumber yang benar juga yang paling masuk akal: tiap detail menggantung
     * pada tanggalnya sendiri, dan tanggal itulah yang tahu periodenya
     * (ADR 0014).
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
