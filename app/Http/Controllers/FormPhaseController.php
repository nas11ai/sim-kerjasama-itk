<?php

namespace App\Http\Controllers;

use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\ReviseFormPhase;
use App\Http\Requests\Form\FormPhaseRequest;
use App\Models\FieldType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Queries\Form\FormPhaseFormOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormPhaseController extends Controller
{
    public function create()
    {
        return Inertia::render('FormPhases/CreatePage', FormPhaseFormOptions::run());
    }

    public function store(FormPhaseRequest $request)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        CreateFormPhase::run($request->toInput(), $actor);

        return redirect()->route('admin.forms.index')
            ->with('success', 'Tahap Formulir berhasil dibuat.');
    }

    public function show(FormPhase $formPhase)
    {
        $formPhase->load([
            'formPhaseDetails' => fn ($details) => $details->withCount(FormPhaseDetail::EVALUATION_FORM_COUNTS),
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType',
            'formPhaseDetails.reviewEvaluationForms' => function ($query) {
                $query->active()->ordered()->withCount('reviewFormFields as fields_count')
                    ->withCount([
                        'reviewFormFields as required_fields_count' => function ($q) {
                            $q->where('is_required', true);
                        },
                    ]);
            },
        ]);

        // Hitung total dari seluruh detail
        $formPhase->review_evaluation_forms_count = $formPhase->formPhaseDetails->sum(
            fn ($detail) => $detail->review_evaluation_forms_count
        );

        $formPhase->required_review_evaluation_forms_count = $formPhase->formPhaseDetails->sum(
            fn ($detail) => $detail->required_review_evaluation_forms_count
        );

        return Inertia::render('FormPhases/ShowPage', [
            'formPhase' => $formPhase,
        ]);
    }

    public function edit(FormPhase $formPhase)
    {
        $formPhase->load([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType',
        ]);

        return Inertia::render('FormPhases/EditPage', [
            'formPhase' => $formPhase,
            ...FormPhaseFormOptions::run($formPhase),
        ]);
    }

    public function update(FormPhaseRequest $request, FormPhase $formPhase)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        // BR-FE-20 — disinkronkan per id, bukan dihapus lalu dibuat ulang.
        ReviseFormPhase::run($formPhase, $request->toInput(), $actor);

        return redirect()->route('admin.forms.index')
            ->with('success', 'Tahap Formulir berhasil diperbarui.');
    }

    /**
     * Show evaluation forms management page for a specific form phase detail
     */
    public function evaluationForms(FormPhase $formPhase, Request $request)
    {
        // Get the form phase detail ID from request
        $formPhaseDetailId = $request->get('detail_id');

        if (!$formPhaseDetailId) {
            // If no detail specified, redirect to show page
            return redirect()->route('admin.form-phases.show', $formPhase)
                ->with('info', 'Silakan pilih detail tahap formulir untuk mengelola formulir evaluasi.');
        }

        $formPhaseDetail = FormPhaseDetail::with([
            'formAccessControl.form',
            'formAccessControl.studyProgram.faculty',
            'phaseType',
            'reviewEvaluationForms' => function ($query) {
                $query->ordered()->withCount('reviewFormFields');
            },
        ])->findOrFail($formPhaseDetailId);

        // Make sure the detail belongs to this phase
        if ($formPhaseDetail->form_phase_id !== $formPhase->id) {
            abort(404);
        }

        $fieldTypes = FieldType::orderBy('name')->get(['id', 'name']);

        return Inertia::render('FormPhases/EvaluationForms', [
            'formPhase' => $formPhase,
            'formPhaseDetail' => $formPhaseDetail,
            'fieldTypes' => $fieldTypes,
        ]);
    }
}
