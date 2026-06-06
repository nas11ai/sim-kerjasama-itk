<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormType;
use App\Models\PhaseType;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CompleteFormBuilderController extends Controller
{
    /**
     * Show the complete form builder page
     */
    public function create()
    {
        return Inertia::render('FormBuilder/Create', [
            'formTypes' => FormType::orderBy('name')->get(),
            'fieldTypes' => FieldType::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'faculties' => Faculty::with('studyPrograms')->orderBy('name')->get(),
            'phaseTypes' => PhaseType::all(),
            'formPhases' => FormPhase::where('is_active', true)
                ->with('formPhaseDetails')
                ->orderBy('title')
                ->get(),
            'submissionPeriods' => SubmissionPeriod::with('submissionDates')
                ->whereHas('submissionDates', function ($query) {
                    $query->where('datetime', '>=', now());
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Store the complete form with all configurations
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Step 1: Basic Form Info
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.form_type_id' => 'required|exists:form_types,id',
            'form.is_active' => 'boolean',
            'form.fields' => 'array',
            'form.fields.*.field_type_id' => 'required|exists:field_types,id',
            'form.fields.*.label' => 'required|string|max:255',
            'form.fields.*.is_required' => 'boolean',
            'form.fields.*.options' => 'array',
            'form.fields.*.options.*.label' => 'required|string|max:255',

            // Step 2: Access Control
            'access_controls' => 'required|array|min:1',
            'access_controls.*.role_id' => 'required|exists:roles,id',
            'access_controls.*.study_program_id' => 'required|exists:study_programs,id',

            // Step 3: Form Phase
            'phase.use_existing' => 'required|boolean',
            'phase.existing_phase_id' => 'nullable|required_if:phase.use_existing,true|exists:form_phases,id',
            'phase.new_phase_title' => 'nullable|required_if:phase.use_existing,false|string|max:255',
            'phase.new_phase_description' => 'nullable|string',
            'phase.phase_type_id' => 'required|exists:phase_types,id',
            'phase.needs_review' => 'boolean',

            // Step 4: Review Evaluation (optional)
            'evaluation_forms' => 'nullable|array',
            'evaluation_forms.*.title' => 'required|string|max:255',
            'evaluation_forms.*.description' => 'nullable|string',
            'evaluation_forms.*.is_required' => 'boolean',
            'evaluation_forms.*.fields' => 'required|array',

            // Step 5: Submission Period
            'submission_period.use_existing' => 'required|boolean',
            'submission_period.existing_period_id' => 'nullable|required_if:submission_period.use_existing,true|exists:submission_periods,id',
            'submission_period.new_period_name' => 'nullable|required_if:submission_period.use_existing,false|string|max:255',
            'submission_period.dates' => 'nullable|required_if:submission_period.use_existing,false|array',
        ]);

        try {
            DB::beginTransaction();

            // Step 1: Create Form
            $form = Form::create([
                'title' => $validated['form']['title'],
                'description' => $validated['form']['description'],
                'form_type_id' => $validated['form']['form_type_id'],
                'is_active' => $validated['form']['is_active'] ?? true,
            ]);

            // Create form fields
            if (isset($validated['form']['fields'])) {
                foreach ($validated['form']['fields'] as $index => $fieldData) {
                    $field = $form->formFields()->create([
                        'field_type_id' => $fieldData['field_type_id'],
                        'label' => $fieldData['label'],
                        'is_required' => $fieldData['is_required'] ?? false,
                        'order' => $index + 1,
                    ]);

                    if (isset($fieldData['options'])) {
                        foreach ($fieldData['options'] as $optionIndex => $optionData) {
                            $field->formFieldOptions()->create([
                                'label' => $optionData['label'],
                                'order' => $optionIndex + 1,
                            ]);
                        }
                    }
                }
            }

            // Step 2: Create Access Controls
            $accessControlIds = [];
            foreach ($validated['access_controls'] as $accessControl) {
                $control = FormAccessControl::create([
                    'form_id' => $form->id,
                    'role_id' => $accessControl['role_id'],
                    'study_program_id' => $accessControl['study_program_id'],
                ]);
                $accessControlIds[] = $control->id;
            }

            // Step 3: Handle Form Phase
            if ($validated['phase']['use_existing']) {
                $formPhase = FormPhase::findOrFail($validated['phase']['existing_phase_id']);
            } else {
                $formPhase = FormPhase::create([
                    'title' => $validated['phase']['new_phase_title'],
                    'description' => $validated['phase']['new_phase_description'] ?? null,
                    'is_active' => true,
                ]);
            }

            // Create Form Phase Details for each access control
            // All access controls are for the same form, so they should have the same order number
            $maxOrder = $formPhase->formPhaseDetails()->max('order');

            $nextOrder = $maxOrder ? $maxOrder + 1 : 1;

            foreach ($accessControlIds as $controlId) {
                $phaseDetail = FormPhaseDetail::create([
                    'form_phase_id' => $formPhase->id,
                    'form_access_control_id' => $controlId,
                    'phase_type_id' => $validated['phase']['phase_type_id'],
                    'needs_review' => $validated['phase']['needs_review'] ?? false,
                    'order' => $nextOrder, // Same order for all access controls of same form
                ]);

                // Step 4: Create Evaluation Forms if needed
                if (
                    isset($validated['evaluation_forms']) &&
                    !empty($validated['evaluation_forms']) &&
                    ($validated['phase']['needs_review'] ?? false)
                ) {
                    foreach ($validated['evaluation_forms'] as $evalIndex => $evalForm) {
                        $evaluationForm = ReviewEvaluationForm::create([
                            'title' => $evalForm['title'],
                            'description' => $evalForm['description'] ?? null,
                            'form_phase_detail_id' => $phaseDetail->id,
                            'is_required' => $evalForm['is_required'] ?? true,
                            'is_active' => true,
                            'order' => $evalIndex + 1,
                        ]);

                        // Create evaluation form fields
                        if (isset($evalForm['fields'])) {
                            foreach ($evalForm['fields'] as $fieldIndex => $evalField) {
                                $reviewField = $evaluationForm->reviewFormFields()->create([
                                    'field_type_id' => $evalField['field_type_id'],
                                    'label' => $evalField['label'],
                                    'description' => $evalField['description'] ?? null,
                                    'is_required' => $evalField['is_required'] ?? false,
                                    'order' => $fieldIndex + 1,
                                    'validation_rules' => $evalField['validation_rules'] ?? null,
                                ]);

                                if (isset($evalField['options'])) {
                                    foreach ($evalField['options'] as $optIndex => $option) {
                                        $reviewField->reviewFormFieldOptions()->create([
                                            'label' => $option['label'],
                                            'value' => $option['value'] ?? $option['label'],
                                            'order' => $optIndex + 1,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Step 5: Handle Submission Period
            if ($validated['submission_period']['use_existing']) {
                $submissionPeriod = SubmissionPeriod::findOrFail($validated['submission_period']['existing_period_id']);

                // Add form phase to existing period
                $submissionPeriod->submissionPeriodPhases()->create([
                    'form_phase_id' => $formPhase->id,
                ]);
            } else {
                // Create new submission period
                $submissionPeriod = SubmissionPeriod::create([
                    'name' => $validated['submission_period']['new_period_name'],
                ]);

                // Create submission dates
                if (isset($validated['submission_period']['dates'])) {
                    foreach ($validated['submission_period']['dates'] as $dateData) {
                        $submissionPeriod->submissionDates()->create([
                            'submission_date_label_id' => $dateData['label_id'],
                            'datetime' => $dateData['date'],
                        ]);
                    }
                }

                // Link form phase to period
                $submissionPeriod->submissionPeriodPhases()->create([
                    'form_phase_id' => $formPhase->id,
                ]);
            }

            DB::commit();

            return redirect()->route('admin.forms.show', $form->id)
                ->with('success', 'Form dan seluruh konfigurasinya berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollback();

            return back()
                ->withErrors(['error' => 'Gagal membuat formulir: ' . $e->getMessage()])
                ->withInput();
        }
    }
}
