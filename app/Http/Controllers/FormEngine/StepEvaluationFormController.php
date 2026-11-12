<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Review\AttachEvaluationForm;
use App\Actions\Review\DetachEvaluationForm;
use App\Actions\Review\UpdateStepEvaluationForm;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\AttachEvaluationFormRequest;
use App\Http\Requests\Review\StepEvaluationFormRequest;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\StepEvaluationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BR-FE-30 — pemasangan Form Evaluasi pustaka di langkah, dari bagian evaluasi editor tahap.
 * Langkahnya dari route (W100); semua aksi kembali ke editor asal.
 */
class StepEvaluationFormController extends Controller
{
    use ResolvesActor;

    public function store(AttachEvaluationFormRequest $request, FormPhaseDetail $formPhaseDetail): RedirectResponse
    {
        $form = ReviewEvaluationForm::query()->findOrFail($request->integer('review_evaluation_form_id'));

        AttachEvaluationForm::run($formPhaseDetail, $form, $request->boolean('is_required', true), $this->actor($request));

        return back()->with('success', 'Form evaluasi dipasang.');
    }

    public function update(StepEvaluationFormRequest $request, StepEvaluationForm $stepEvaluationForm): RedirectResponse
    {
        UpdateStepEvaluationForm::run(
            $stepEvaluationForm,
            $request->boolean('is_required'),
            $request->boolean('is_active'),
            $this->actor($request),
        );

        return back()->with('success', 'Pemasangan diperbarui.');
    }

    // BR-FE-22 — pemasangan yang sudah ditugaskan di langkah ini ditolak DetachEvaluationForm.
    public function destroy(Request $request, StepEvaluationForm $stepEvaluationForm): RedirectResponse
    {
        DetachEvaluationForm::run($stepEvaluationForm, $this->actor($request));

        return back()->with('success', 'Form evaluasi dilepas.');
    }
}
