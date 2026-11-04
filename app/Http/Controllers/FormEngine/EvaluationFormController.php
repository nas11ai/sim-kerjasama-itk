<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Review\CreateReviewEvaluationForm;
use App\Actions\Review\DeleteReviewEvaluationForm;
use App\Actions\Review\ReviseReviewEvaluationForm;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReviewEvaluationFormRequest;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kartu form evaluasi di editor tahap ADMIN-08-09, disimpan per kartu (W95). Route dijaga
 * `can:schemes.manage` (W88); detailnya dari route, jadi form tidak bisa dipindah (W100).
 */
class EvaluationFormController extends Controller
{
    use ResolvesActor;

    // Binding `formPhaseDetail` memberi 404 untuk detail tak dikenal; request membaca detailnya dari
    // route, dan detail tak direview ditolak AssertReviewedPhaseDetail di Action, apa adanya.
    public function store(ReviewEvaluationFormRequest $request, FormPhaseDetail $formPhaseDetail): RedirectResponse
    {
        CreateReviewEvaluationForm::run($request->toInput(), $this->actor($request));

        return back()->with('success', 'Form evaluasi dibuat.');
    }

    public function update(ReviewEvaluationFormRequest $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        ReviseReviewEvaluationForm::run($reviewEvaluationForm, $request->toInput($reviewEvaluationForm), $this->actor($request));

        return back()->with('success', 'Form evaluasi diperbarui.');
    }

    // BR-FE-22 — penolakan form yang pernah ditugaskan datang dari Action sebagai galat validasi.
    public function destroy(Request $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        DeleteReviewEvaluationForm::run($reviewEvaluationForm, $this->actor($request));

        return back()->with('success', 'Form evaluasi dihapus.');
    }
}
