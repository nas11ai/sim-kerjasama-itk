<?php

namespace App\Http\Controllers;

use App\Actions\Review\CreateReviewEvaluationForm;
use App\Actions\Review\DeleteReviewEvaluationForm;
use App\Actions\Review\DuplicateReviewEvaluationForm;
use App\Actions\Review\ReorderReviewEvaluationForms;
use App\Actions\Review\ReviseReviewEvaluationForm;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Review\ReviewEvaluationFormListRequest;
use App\Http\Requests\Review\ReviewEvaluationFormOrderRequest;
use App\Http\Requests\Review\ReviewEvaluationFormRequest;
use App\Models\ReviewEvaluationForm;
use App\Queries\Review\EvaluationFormUsage;
use App\Queries\Review\ReviewEvaluationFormChoices;
use App\Queries\Review\ReviewEvaluationFormDirectory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewEvaluationFormController extends Controller
{
    use ResolvesActor;

    public function index(ReviewEvaluationFormListRequest $request)
    {
        $directory = ReviewEvaluationFormDirectory::run(
            $request->idFilter('form_phase_id'),
            $request->idFilter('form_phase_detail_id'),
            $request->search(),
        );

        return Inertia::render('ReviewEvaluationForms/IndexPage', [
            ...$directory,
            'filters' => $request->only(['form_phase_id', 'form_phase_detail_id', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('ReviewEvaluationForms/CreatePage', ReviewEvaluationFormChoices::run());
    }

    public function store(ReviewEvaluationFormRequest $request)
    {
        CreateReviewEvaluationForm::run($request->toInput(), $this->actor($request));

        return redirect()->back()
            ->with('success', 'Formulir evaluasi review berhasil dibuat.');
    }

    public function show(ReviewEvaluationForm $reviewEvaluationForm)
    {
        $this->loadFields($reviewEvaluationForm, [
            'formPhaseDetail.formPhase:id,title,description',
            'formPhaseDetail.formAccessControl.form',
        ]);

        return Inertia::render('ReviewEvaluationForms/ShowPage', [
            'evaluationForm' => $reviewEvaluationForm,
            'assignmentStats' => EvaluationFormUsage::run($reviewEvaluationForm),
        ]);
    }

    public function edit(ReviewEvaluationForm $reviewEvaluationForm)
    {
        $this->loadFields($reviewEvaluationForm, [
            'formPhaseDetail.formPhase',
            'formPhaseDetail.formAccessControl.form',
        ]);

        return Inertia::render('ReviewEvaluationForms/EditPage', [
            'evaluationForm' => $reviewEvaluationForm,
            ...ReviewEvaluationFormChoices::run(),
        ]);
    }

    public function update(ReviewEvaluationFormRequest $request, ReviewEvaluationForm $reviewEvaluationForm)
    {
        ReviseReviewEvaluationForm::run($reviewEvaluationForm, $request->toInput($reviewEvaluationForm), $this->actor($request));

        return redirect()->back()
            ->with('success', 'Formulir evaluasi review berhasil diperbarui.');
    }

    public function destroy(Request $request, ReviewEvaluationForm $reviewEvaluationForm)
    {
        // BR-FE-22 — form yang pernah ditugaskan ditolak beserta jumlah penugasannya.
        DeleteReviewEvaluationForm::run($reviewEvaluationForm, $this->actor($request));

        return redirect()->route('admin.review-evaluation-forms.index')
            ->with('success', 'Formulir evaluasi review berhasil dihapus.');
    }

    public function duplicate(Request $request, ReviewEvaluationForm $reviewEvaluationForm)
    {
        $copy = DuplicateReviewEvaluationForm::run($reviewEvaluationForm, $this->actor($request));

        return redirect()->route('admin.review-evaluation-forms.edit', $copy)
            ->with('success', 'Formulir evaluasi review berhasil diduplikasi.');
    }

    public function updateOrder(ReviewEvaluationFormOrderRequest $request)
    {
        ReorderReviewEvaluationForms::run($request->orders(), $this->actor($request));

        // Dipanggil lewat useForm Inertia; balasan JSON dulu ditolak Inertia sebagai galat.
        return redirect()->back()->with('success', 'Urutan berhasil diperbarui.');
    }

    public function preview(ReviewEvaluationForm $reviewEvaluationForm)
    {
        $this->loadFields($reviewEvaluationForm);

        return Inertia::render('ReviewEvaluationForms/PreviewPage', [
            'evaluationForm' => $reviewEvaluationForm,
        ]);
    }

    /** @param  list<string>  $relations */
    private function loadFields(ReviewEvaluationForm $form, array $relations = []): void
    {
        $form->load([
            ...$relations,
            'reviewFormFields' => fn (Relation $fields) => $fields->orderBy('order')->with([
                'fieldType:id,name',
                'reviewFormFieldOptions' => fn (Relation $options) => $options->orderBy('order'),
            ]),
        ]);
    }
}
