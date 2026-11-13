<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Review\DiscardEvaluationFormDraft;
use App\Actions\Review\PublishEvaluationFormDraft;
use App\Actions\Review\SaveEvaluationFormDraft;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\EvaluationDraftRequest;
use App\Models\ReviewEvaluationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** BR-FE-31 — draf isi Formulir Evaluasi: simpan, buang, terbitkan menjadi versi berikutnya. */
class EvaluationFormDraftController extends Controller
{
    use ResolvesActor;

    public function update(EvaluationDraftRequest $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        SaveEvaluationFormDraft::run($reviewEvaluationForm, $request->toInput(), $this->actor($request));

        return back()->with('success', 'Draf disimpan.');
    }

    public function destroy(Request $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        DiscardEvaluationFormDraft::run($reviewEvaluationForm, $this->actor($request));

        return back()->with('success', 'Draf dibuang.');
    }

    public function publish(Request $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        $version = PublishEvaluationFormDraft::run($reviewEvaluationForm, $this->actor($request));

        return back()->with('success', "{$version->label()} diterbitkan.");
    }
}
