<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\PhaseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'formPhaseDetails.formAccessControl.role',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType',
            'formPhaseDetails.reviewEvaluationForms', // Changed: now loaded through formPhaseDetails
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
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
                fn ($detail) => $detail->reviewEvaluationFormsCount
            );

            $phase->required_review_evaluation_forms_count = $phase->formPhaseDetails->sum(
                fn ($detail) => $detail->requiredReviewEvaluationFormsCount
            );

            return $phase;
        });

        return Inertia::render('FormPhases/Index', [
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
        $faculties = Faculty::with('studyPrograms')->get();
        $phaseTypes = PhaseType::all(['id', 'name']);
        $formAccessControls = FormAccessControl::with(['form', 'role', 'studyProgram'])
            ->get();

        return Inertia::render('FormPhases/Create', [
            'forms' => $forms,
            'roles' => $roles,
            'faculties' => $faculties,
            'phaseTypes' => $phaseTypes,
            'formAccessControls' => $formAccessControls,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'phase_details' => 'required|array|min:1',
            'phase_details.*.form_access_control_id' => 'required|exists:form_access_controls,id',
            'phase_details.*.phase_type_id' => 'required|exists:phase_types,id',
            'phase_details.*.order' => 'required|integer|min:1',
            'phase_details.*.needs_review' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $formPhase = FormPhase::create([
                'title' => $request->title,
                'description' => $request->description,
                'is_active' => $request->is_active ?? false,
            ]);

            // Normalize order: group by form_id and assign same order to same forms
            $phaseDetails = collect($request->phase_details);

            // Get form_id for each detail
            $detailsWithFormId = $phaseDetails->map(function ($detail) {
                $formAccessControl = FormAccessControl::with('form')->find($detail['form_access_control_id']);

                return array_merge($detail, [
                    'form_id' => $formAccessControl->form_id,
                ]);
            });

            // Calculate normalized order based on unique form_ids in sequence
            $seenFormIds = [];
            $formIdToOrder = [];
            $currentOrder = 1;

            foreach ($detailsWithFormId as $detail) {
                $formId = $detail['form_id'];
                if (!in_array($formId, $seenFormIds)) {
                    $seenFormIds[] = $formId;
                    $formIdToOrder[$formId] = $currentOrder;
                    $currentOrder++;
                }
            }

            // Create phase details with normalized order
            foreach ($detailsWithFormId as $detail) {
                $normalizedOrder = $formIdToOrder[$detail['form_id']];

                FormPhaseDetail::create([
                    'form_phase_id' => $formPhase->id,
                    'form_access_control_id' => $detail['form_access_control_id'],
                    'phase_type_id' => $detail['phase_type_id'],
                    'order' => $normalizedOrder, // Use normalized order based on form_id
                    'needs_review' => $detail['needs_review'] ?? false,
                ]);
            }

            DB::commit();

            return redirect()->route('admin.form-phases.index')
                ->with('success', 'Tahap Formulir berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal membuat tahap formulir: '.$e->getMessage()]);
        }
    }

    public function show(FormPhase $formPhase)
    {
        $formPhase->load([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.role',
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

        return Inertia::render('FormPhases/Show', [
            'formPhase' => $formPhase,
        ]);
    }

    public function edit(FormPhase $formPhase)
    {
        $formPhase->load([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.role',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType',
        ]);

        $forms = Form::where('is_active', true)->get(['id', 'title']);
        $roles = Role::all(['id', 'name']);
        $faculties = Faculty::with('studyPrograms')->get();
        $phaseTypes = PhaseType::all(['id', 'name']);
        $formAccessControls = FormAccessControl::with(['form', 'role', 'studyProgram'])
            ->get();

        return Inertia::render('FormPhases/Edit', [
            'formPhase' => $formPhase,
            'forms' => $forms,
            'roles' => $roles,
            'faculties' => $faculties,
            'phaseTypes' => $phaseTypes,
            'formAccessControls' => $formAccessControls,
        ]);
    }

    public function update(Request $request, FormPhase $formPhase)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'phase_details' => 'required|array|min:1',
            'phase_details.*.form_access_control_id' => 'required|exists:form_access_controls,id',
            'phase_details.*.phase_type_id' => 'required|exists:phase_types,id',
            'phase_details.*.order' => 'required|integer|min:1',
            'phase_details.*.needs_review' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $formPhase->update([
                'title' => $request->title,
                'description' => $request->description,
                'is_active' => $request->is_active ?? false,
            ]);

            // Delete existing phase details
            $formPhase->formPhaseDetails()->delete();

            // Create new phase details with normalized order
            // Normalize order: group by form_id and assign same order to same forms
            $phaseDetails = collect($request->phase_details);

            // Get form_id for each detail
            $detailsWithFormId = $phaseDetails->map(function ($detail) {
                $formAccessControl = FormAccessControl::with('form')->find($detail['form_access_control_id']);

                return array_merge($detail, [
                    'form_id' => $formAccessControl->form_id,
                ]);
            });

            // Calculate normalized order based on unique form_ids in sequence
            $seenFormIds = [];
            $formIdToOrder = [];
            $currentOrder = 1;

            foreach ($detailsWithFormId as $detail) {
                $formId = $detail['form_id'];
                if (!in_array($formId, $seenFormIds)) {
                    $seenFormIds[] = $formId;
                    $formIdToOrder[$formId] = $currentOrder;
                    $currentOrder++;
                }
            }

            // Create phase details with normalized order
            foreach ($detailsWithFormId as $detail) {
                $normalizedOrder = $formIdToOrder[$detail['form_id']];

                FormPhaseDetail::create([
                    'form_phase_id' => $formPhase->id,
                    'form_access_control_id' => $detail['form_access_control_id'],
                    'phase_type_id' => $detail['phase_type_id'],
                    'needs_review' => $detail['needs_review'] ?? false,
                    'order' => $normalizedOrder, // Use normalized order based on form_id
                ]);
            }

            DB::commit();

            return redirect()->route('admin.form-phases.index')
                ->with('success', 'Tahap Formulir berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal memperbarui tahap formulir: '.$e->getMessage()]);
        }
    }

    public function destroy(FormPhase $formPhase)
    {
        try {
            DB::beginTransaction();

            $formPhase->formPhaseDetails()->delete();
            $formPhase->delete();

            DB::commit();

            return redirect()->route('admin.form-phases.index')
                ->with('success', 'Tahap Formulir berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus tahap formulir: '.$e->getMessage()]);
        }
    }

    public function getFormAccessControls(Request $request)
    {
        $query = FormAccessControl::with(['form', 'role', 'studyProgram.faculty']);

        if ($request->has('form_id')) {
            $query->where('form_id', $request->form_id);
        }

        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->has('study_program_id')) {
            $query->where('study_program_id', $request->study_program_id);
        }

        $formAccessControls = $query->get();

        return response()->json($formAccessControls);
    }

    public function updateStatus(Request $request, FormPhase $formPhase)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        try {
            $formPhase->update([
                'is_active' => $request->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diperbarui',
                'is_active' => $formPhase->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status: '.$e->getMessage(),
            ], 500);
        }
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
            'formAccessControl.role',
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
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:form_phases,id',
        ]);

        try {
            DB::beginTransaction();

            $formPhases = FormPhase::whereIn('id', $request->ids)->get();

            foreach ($formPhases as $formPhase) {
                $formPhase->formPhaseDetails()->delete();
                $formPhase->delete();
            }

            DB::commit();

            return redirect()->route('admin.form-phases.index')
                ->with('success', 'Tahap Formulir terpilih berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Gagal menghapus tahap formulir terpilih: '.$e->getMessage()]);
        }
    }
}
