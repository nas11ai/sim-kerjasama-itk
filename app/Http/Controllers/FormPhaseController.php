<?php

namespace App\Http\Controllers;

use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\DeleteFormPhase;
use App\Actions\Form\ReviseFormPhase;
use App\Actions\Form\SetFormPhaseActive;
use App\Http\Requests\Form\FormPhaseListRequest;
use App\Http\Requests\Form\FormPhaseRequest;
use App\Models\FieldType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Queries\Form\FormPhaseDirectory;
use App\Queries\Form\FormPhaseFormOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FormPhaseController extends Controller
{
    public function index(FormPhaseListRequest $request)
    {
        $directory = FormPhaseDirectory::run($request->toInput());

        return Inertia::render('FormPhases/IndexPage', [
            'formPhases' => $directory['phases'],
            'filters' => $directory['filters'],
        ]);
    }

    public function create()
    {
        return Inertia::render('FormPhases/CreatePage', FormPhaseFormOptions::run());
    }

    public function store(FormPhaseRequest $request)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        CreateFormPhase::run($request->toInput(), $actor);

        return redirect()->route('admin.form-phases.index')
            ->with('success', 'Tahap Formulir berhasil dibuat.');
    }

    public function show(FormPhase $formPhase)
    {
        $formPhase->load([
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
            fn ($detail) => $detail->reviewEvaluationFormsCount
        );

        $formPhase->required_review_evaluation_forms_count = $formPhase->formPhaseDetails->sum(
            fn ($detail) => $detail->requiredReviewEvaluationFormsCount
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

        return redirect()->route('admin.form-phases.index')
            ->with('success', 'Tahap Formulir berhasil diperbarui.');
    }

    public function destroy(Request $request, FormPhase $formPhase)
    {
        $actor = $request->user();

        abort_unless($actor !== null, 403);

        // BR-FE-20 — penjaga yang sama dengan hapus massal; penolakannya validasi.
        DeleteFormPhase::run($formPhase, $actor);

        return redirect()->route('admin.form-phases.index')
            ->with('success', 'Tahap Formulir berhasil dihapus.');
    }

    public function updateStatus(Request $request, FormPhase $formPhase)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $actor = $request->user();

        abort_unless($actor !== null, 403);

        SetFormPhaseActive::run($formPhase, $request->boolean('is_active'), $actor);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diperbarui',
            'is_active' => $formPhase->is_active,
        ]);
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

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:form_phases,id',
        ]);

        $actor = Auth::user();

        abort_unless($actor !== null, 403);

        // BR-FE-20 — penjaga di Action, sebelum apa pun disentuh; dulu foreign key yang
        // menolak, jadi Admin menerima SQLSTATE mentah. Semua atau tidak sama sekali: satu
        // tahap terpakai menggulung kembali tahap yang sudah terhapus beserta auditnya.
        DB::transaction(fn () => FormPhase::whereIn('id', $validated['ids'])
            ->get()
            ->each(fn (FormPhase $phase) => DeleteFormPhase::run($phase, $actor)));

        return redirect()->route('admin.form-phases.index')
            ->with('success', 'Tahap Formulir terpilih berhasil dihapus.');
    }
}
