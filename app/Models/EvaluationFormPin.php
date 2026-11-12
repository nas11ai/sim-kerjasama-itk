<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BR-REV-18 — versi yang dipakai satu angkatan × langkah × Form Evaluasi, dipaku saat draf
 * penilaian pertama di sana tersimpan. Unik per (periode, langkah, form).
 *
 * @property int $id
 * @property int $submission_period_id
 * @property int $form_phase_detail_id
 * @property int $review_evaluation_form_id
 * @property int $review_evaluation_form_version_id
 * @property Carbon $pinned_at
 * @property-read ReviewEvaluationFormVersion $reviewEvaluationFormVersion
 */
class EvaluationFormPin extends Model
{
    protected $fillable = [
        'submission_period_id',
        'form_phase_detail_id',
        'review_evaluation_form_id',
        'review_evaluation_form_version_id',
        'pinned_at',
    ];

    protected $casts = [
        'pinned_at' => 'datetime',
    ];

    /** @return BelongsTo<SubmissionPeriod, $this> */
    public function submissionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubmissionPeriod::class);
    }

    /** @return BelongsTo<FormPhaseDetail, $this> langkah angkatan */
    public function formPhaseDetail(): BelongsTo
    {
        return $this->belongsTo(FormPhaseDetail::class);
    }

    /** @return BelongsTo<ReviewEvaluationFormVersion, $this> versi pakuan */
    public function reviewEvaluationFormVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationFormVersion::class);
    }

    /** @return BelongsTo<ReviewEvaluationForm, $this> */
    public function reviewEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationForm::class);
    }
}
