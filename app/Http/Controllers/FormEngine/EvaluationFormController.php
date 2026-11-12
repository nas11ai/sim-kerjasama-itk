<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Review\CreateEvaluationForm;
use App\Actions\Review\DeleteEvaluationForm;
use App\Actions\Review\UpdateEvaluationForm;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\EvaluationFormRequest;
use App\Models\ReviewEvaluationForm;
use App\Queries\Form\FieldTypeOptions;
use App\Queries\Review\EvaluationFormEdit;
use App\Queries\Review\EvaluationFormLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pustaka Form Evaluasi (ADMIN-08-09, BR-FE-30): daftar, buat, ubah nama, hapus. Isinya disunting
 * lewat draf di EvaluationFormDraftController (BR-FE-31). Route dijaga `can:schemes.manage` (W88).
 */
class EvaluationFormController extends Controller
{
    use ResolvesActor;

    public function index(): Response
    {
        return Inertia::render('FormEngine/EvaluationFormIndexPage', ['forms' => EvaluationFormLibrary::run()]);
    }

    // Mode buat = editor yang sama tanpa form: hanya judul dan deskripsi (W94).
    public function create(): Response
    {
        return Inertia::render('FormEngine/EvaluationFormEditPage', ['form' => null, 'fieldTypeOptions' => []]);
    }

    public function store(EvaluationFormRequest $request): RedirectResponse
    {
        $form = CreateEvaluationForm::run($request->toInput(), $this->actor($request));

        return redirect()->route('admin.evaluation-forms.edit', $form)
            ->with('success', 'Form evaluasi dibuat. Susun field-nya lalu terbitkan.');
    }

    public function edit(ReviewEvaluationForm $reviewEvaluationForm): Response
    {
        return Inertia::render('FormEngine/EvaluationFormEditPage', [
            'form' => EvaluationFormEdit::run($reviewEvaluationForm),
            'fieldTypeOptions' => FieldTypeOptions::run(),
        ]);
    }

    public function update(EvaluationFormRequest $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        UpdateEvaluationForm::run($reviewEvaluationForm, $request->toInput(), $this->actor($request));

        return back()->with('success', 'Form evaluasi diperbarui.');
    }

    // BR-FE-22 — penolakan form yang pernah ditugaskan atau masih terpasang datang dari penjaga model.
    public function destroy(Request $request, ReviewEvaluationForm $reviewEvaluationForm): RedirectResponse
    {
        DeleteEvaluationForm::run($reviewEvaluationForm, $this->actor($request));

        return redirect()->route('admin.evaluation-forms.index')->with('success', 'Form evaluasi dihapus.');
    }
}
