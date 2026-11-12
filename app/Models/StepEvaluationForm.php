<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Pemasangan Form Evaluasi pustaka di satu langkah `needs_review` (G2-W2): wajib, aktif, dan
 * urutannya milik pemasangan, bukan milik formnya. BR-FE-32 — bagian versi struktur yang beku.
 *
 * @property int $id
 * @property int $form_phase_detail_id
 * @property int $review_evaluation_form_id
 * @property bool $is_required
 * @property bool $is_active
 * @property int $order
 * @property-read FormPhaseDetail $formPhaseDetail
 * @property-read ReviewEvaluationForm $reviewEvaluationForm
 * @property-read int|null $assignment_count hanya setelah subquery PhaseEvaluationForms
 */
class StepEvaluationForm extends Model
{
    protected $fillable = [
        'form_phase_detail_id',
        'review_evaluation_form_id',
        'is_required',
        'is_active',
        'order',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Pemasangan form evaluasi di versi struktur yang terbit tidak dapat diubah.');
        });
    }

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /** @return BelongsTo<FormPhaseDetail, $this> */
    public function formPhaseDetail(): BelongsTo
    {
        return $this->belongsTo(FormPhaseDetail::class);
    }

    /** @return BelongsTo<ReviewEvaluationForm, $this> */
    public function reviewEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(ReviewEvaluationForm::class);
    }

    /**
     * @param  Builder<StepEvaluationForm>  $query
     * @return Builder<StepEvaluationForm>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('step_evaluation_forms.is_active', true);
    }

    /**
     * @param  Builder<StepEvaluationForm>  $query
     * @return Builder<StepEvaluationForm>
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('step_evaluation_forms.is_required', true);
    }

    /**
     * @param  Builder<StepEvaluationForm>  $query
     * @return Builder<StepEvaluationForm>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('step_evaluation_forms.order')->orderBy('step_evaluation_forms.id');
    }
}
