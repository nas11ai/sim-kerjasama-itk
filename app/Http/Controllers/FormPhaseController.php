<?php

namespace App\Http\Controllers;

use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormAccessControl;
use App\Models\PhaseType;
use App\Models\Form;
use App\Models\StudyProgram;
use App\Models\Faculty;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class FormPhaseController extends Controller
{
    public function index()
    {
        $formPhases = FormPhase::with([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.role',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType'
        ])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('FormPhases/Index', [
            'formPhases' => $formPhases
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
            'formAccessControls' => $formAccessControls
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
                'is_active' => $request->is_active ?? false
            ]);

            foreach ($request->phase_details as $detail) {
                FormPhaseDetail::create([
                    'form_phase_id' => $formPhase->id,
                    'form_access_control_id' => $detail['form_access_control_id'],
                    'phase_type_id' => $detail['phase_type_id'],
                    'order' => $detail['order'],
                    'needs_review' => $detail['needs_review'] ?? false,
                ]);
            }

            DB::commit();

            return redirect()->route('form-phases.index')
                ->with('success', 'Form phase created successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to create form phase: ' . $e->getMessage()]);
        }
    }

    public function show(FormPhase $formPhase)
    {
        $formPhase->load([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.role',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType'
        ]);

        return Inertia::render('FormPhases/Show', [
            'formPhase' => $formPhase
        ]);
    }

    public function edit(FormPhase $formPhase)
    {
        $formPhase->load([
            'formPhaseDetails.formAccessControl.form',
            'formPhaseDetails.formAccessControl.role',
            'formPhaseDetails.formAccessControl.studyProgram.faculty',
            'formPhaseDetails.phaseType'
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
            'formAccessControls' => $formAccessControls
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
                'is_active' => $request->is_active ?? false
            ]);

            // Delete existing phase details
            $formPhase->formPhaseDetails()->delete();

            // Create new phase details
            foreach ($request->phase_details as $detail) {
                FormPhaseDetail::create([
                    'form_phase_id' => $formPhase->id,
                    'form_access_control_id' => $detail['form_access_control_id'],
                    'phase_type_id' => $detail['phase_type_id'],
                    'needs_review' => $detail['needs_review'] ?? false,
                    'order' => $detail['order']
                ]);
            }

            DB::commit();

            return redirect()->route('admin.form-phases.index')
                ->with('success', 'Form phase updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to update form phase: ' . $e->getMessage()]);
        }
    }

    public function destroy(FormPhase $formPhase)
    {
        try {
            DB::beginTransaction();

            $formPhase->formPhaseDetails()->delete();
            $formPhase->delete();

            DB::commit();

            return redirect()->route('form-phases.index')
                ->with('success', 'Form phase deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to delete form phase: ' . $e->getMessage()]);
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
            'is_active' => 'required|boolean'
        ]);

        try {
            $formPhase->update([
                'is_active' => $request->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'is_active' => $formPhase->is_active
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }
}
