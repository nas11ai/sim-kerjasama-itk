<?php

namespace App\Http\Controllers;

use App\Actions\Form\CreateFormPhase;
use App\Actions\Form\DeleteFormPhase;
use App\Actions\Form\ReviseFormPhase;
use App\Actions\Form\SetFormPhaseActive;
use App\Http\Requests\Form\FormPhaseRequest;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\Organization;
use App\Models\PhaseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class FormPhaseController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $perPage = $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $isActiveFilter = $request->get('is_active');

        $query = FormPhase::with([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType',
            'formPhaseDetails.reviewEvaluationForms', // Changed: now loaded through formPhaseDetails
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%');
            });
        }

        if ($isActiveFilter && $isActiveFilter !== 'all') {
            $query->where('is_active', $isActiveFilter === 'active' ? 1 : 0);
        }

        $formPhases = $query->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        // Calculate review evaluation forms counts for each phase
        $formPhases->getCollection()->transform(function ($phase) {
            $phase->review_evaluation_forms_count = $phase->formPhaseDetails->sum(
                fn (FormPhaseDetail $detail) => $detail->reviewEvaluationFormsCount
            );

            $phase->required_review_evaluation_forms_count = $phase->formPhaseDetails->sum(
                fn (FormPhaseDetail $detail) => $detail->requiredReviewEvaluationFormsCount
            );

            return $phase;
        });

        return Inertia::render('FormPhases/IndexPage', [
            'formPhases' => $formPhases,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'is_active' => $isActiveFilter,
            ],
        ]);
    }

    public function create()
    {
        $forms = Form::where('is_active', true)->get(['id', 'title']);
        $roles = Role::all(['id', 'name']);
        $faculties = Organization::facultyOptions();
        $phaseTypes = PhaseType::all(['id', 'name']);
        $formAccessControls = FormAccessControl::with(['form', 'studyProgram'])
            ->get();

        return Inertia::render('FormPhases/CreatePage', [
            'forms' => $forms,
            'roles' => $roles,
            'permissions' => FormAccessControl::ALLOWED_PERMISSIONS,
            'faculties' => $faculties,
            'phaseTypes' => $phaseTypes,
            'formAccessControls' => $formAccessControls,
        ]);
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

        $forms = Form::where('is_active', true)->get(['id', 'title']);
        $roles = Role::all(['id', 'name']);
        $faculties = Organization::facultyOptions();
        $phaseTypes = PhaseType::all(['id', 'name']);
        $formAccessControls = FormAccessControl::with(['form', 'studyProgram'])
            ->get();

        return Inertia::render('FormPhases/EditPage', [
            'formPhase' => $formPhase,
            'forms' => $forms,
            'roles' => $roles,
            'permissions' => FormAccessControl::ALLOWED_PERMISSIONS,
            'faculties' => $faculties,
            'phaseTypes' => $phaseTypes,
            'formAccessControls' => $formAccessControls,
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

    public function getFormAccessControls(Request $request)
    {
        $query = FormAccessControl::with(['form', 'studyProgram.faculty']);

        if ($request->has('form_id')) {
            $query->where('form_id', $request->form_id);
        }

        if ($request->filled('permission')) {
            $query->where('permission', $request->permission);
        }

        if ($request->has('study_program_id')) {
            $query->where('organization_id', $request->study_program_id);
        }

        $formAccessControls = $query->get();

        return response()->json($formAccessControls);
    }

    public function updateStatus(Request $request, FormPhase $formPhase)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $actor = $request->user();

        abort_unless($actor !== null, 403);

        SetFormPhaseActive::run($formPhase, (bool) $validated['is_active'], $actor);

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

        // BR-FE-20 — penjagaannya di dalam Action, dijalankan sebelum apa pun
        // disentuh. Versi lama membiarkan foreign key yang menolak, sehingga
        // yang sampai ke Admin adalah SQLSTATE mentah.
        FormPhase::whereIn('id', $validated['ids'])
            ->get()
            ->each(fn (FormPhase $phase) => DeleteFormPhase::run($phase, $actor));

        return redirect()->route('admin.form-phases.index')
            ->with('success', 'Tahap Formulir terpilih berhasil dihapus.');
    }
}
