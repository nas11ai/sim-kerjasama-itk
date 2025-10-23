<?php

namespace App\Http\Controllers;

use App\Models\ReviewEvaluationForm;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldOption;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FieldType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReviewEvaluationFormController extends Controller
{
    public function index(Request $request)
    {
        $query = ReviewEvaluationForm::with([
            'formPhaseDetail.formPhase:id,title',
            'formPhaseDetail.formAccessControl.form:id,title',
            'formPhaseDetail.formAccessControl.role:id,name',
            'reviewFormFields'
        ]);

        // Filter by form phase
        if ($request->has('form_phase_id') && $request->form_phase_id) {
            $query->whereHas('formPhaseDetail', function ($q) use ($request) {
                $q->where('form_phase_id', $request->form_phase_id);
            });
        }

        // Filter by form phase detail
        if ($request->has('form_phase_detail_id') && $request->form_phase_detail_id) {
            $query->where('form_phase_detail_id', $request->form_phase_detail_id);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $evaluationForms = $query->orderBy('order')->paginate(15);

        $formPhases = FormPhase::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        $formPhaseDetails = FormPhaseDetail::with([
            'formPhase:id,title',
            'formAccessControl.form:id,title'
        ])
            ->whereHas('formPhase', function ($q) {
                $q->where('is_active', true);
            })
            ->get();

        return Inertia::render('ReviewEvaluationForms/Index', [
            'evaluationForms' => $evaluationForms,
            'formPhases' => $formPhases,
            'formPhaseDetails' => $formPhaseDetails,
            'filters' => $request->only(['form_phase_id', 'form_phase_detail_id', 'search'])
        ]);
    }

    public function create()
    {
        $formPhases = FormPhase::where('is_active', true)
            ->with([
                'formPhaseDetails.formAccessControl.form',
                'formPhaseDetails.formAccessControl.role',
                'formPhaseDetails.formAccessControl.studyProgram'
            ])
            ->orderBy('title')
            ->get();

        $fieldTypes = FieldType::orderBy('name')->get(['id', 'name']);

        return Inertia::render('ReviewEvaluationForms/Create', [
            'formPhases' => $formPhases,
            'fieldTypes' => $fieldTypes
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_phase_detail_id' => 'required|exists:form_phase_details,id',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.field_type_id' => 'required|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.validation_rules' => 'nullable|array',
            'fields.*.options' => 'array',
            'fields.*.options.*.label' => 'required|string|max:255',
            'fields.*.options.*.value' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Get next order number for this form phase detail
            $nextOrder = ReviewEvaluationForm::where('form_phase_detail_id', $validated['form_phase_detail_id'])
                ->max('order') + 1;

            $evaluationForm = ReviewEvaluationForm::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'form_phase_detail_id' => $validated['form_phase_detail_id'],
                'is_required' => $validated['is_required'] ?? true,
                'is_active' => $validated['is_active'] ?? true,
                'order' => $nextOrder,
            ]);

            // Create fields
            if (isset($validated['fields'])) {
                foreach ($validated['fields'] as $index => $fieldData) {
                    $field = ReviewFormField::create([
                        'review_evaluation_form_id' => $evaluationForm->id,
                        'field_type_id' => $fieldData['field_type_id'],
                        'label' => $fieldData['label'],
                        'description' => $fieldData['description'] ?? null,
                        'is_required' => $fieldData['is_required'] ?? false,
                        'order' => $index + 1,
                        'validation_rules' => $fieldData['validation_rules'] ?? null,
                    ]);

                    // Create field options
                    if (isset($fieldData['options'])) {
                        foreach ($fieldData['options'] as $optionIndex => $optionData) {
                            ReviewFormFieldOption::create([
                                'review_form_field_id' => $field->id,
                                'label' => $optionData['label'],
                                'value' => $optionData['value'] ?? null,
                                'order' => $optionIndex + 1,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->back()
                ->with('success', 'Review evaluation form created successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to create review evaluation form: ' . $e->getMessage()]);
        }
    }

    public function show(ReviewEvaluationForm $reviewEvaluationForm)
    {
        $reviewEvaluationForm->load([
            'formPhaseDetail.formPhase:id,title,description',
            'formPhaseDetail.formAccessControl.form',
            'formPhaseDetail.formAccessControl.role',
            'reviewFormFields' => function ($query) {
                $query->ordered()->with([
                    'fieldType:id,name',
                    'reviewFormFieldOptions' => function ($q) {
                        $q->ordered();
                    }
                ]);
            }
        ]);

        // Get usage statistics
        $assignmentStats = [
            'total_assignments' => $reviewEvaluationForm->reviewerFormAssignments()->count(),
            'completed_responses' => $reviewEvaluationForm->reviewerFormAssignments()
                ->whereHas('reviewFormResponse', function ($q) {
                    $q->where('status', 'submitted');
                })->count(),
            'pending_responses' => $reviewEvaluationForm->reviewerFormAssignments()
                ->whereDoesntHave('reviewFormResponse', function ($q) {
                    $q->where('status', 'submitted');
                })->count(),
        ];

        return Inertia::render('ReviewEvaluationForms/Show', [
            'evaluationForm' => $reviewEvaluationForm,
            'assignmentStats' => $assignmentStats
        ]);
    }

    public function edit(ReviewEvaluationForm $reviewEvaluationForm)
    {
        $reviewEvaluationForm->load([
            'formPhaseDetail.formPhase',
            'formPhaseDetail.formAccessControl.form',
            'reviewFormFields' => function ($query) {
                $query->ordered()->with([
                    'fieldType:id,name',
                    'reviewFormFieldOptions' => function ($q) {
                        $q->ordered();
                    }
                ]);
            }
        ]);

        $formPhases = FormPhase::where('is_active', true)
            ->with([
                'formPhaseDetails.formAccessControl.form',
                'formPhaseDetails.formAccessControl.role',
                'formPhaseDetails.formAccessControl.studyProgram'
            ])
            ->orderBy('title')
            ->get();

        $fieldTypes = FieldType::orderBy('name')->get(['id', 'name']);

        return Inertia::render('ReviewEvaluationForms/Edit', [
            'evaluationForm' => $reviewEvaluationForm,
            'formPhases' => $formPhases,
            'fieldTypes' => $fieldTypes
        ]);
    }

    public function update(Request $request, ReviewEvaluationForm $reviewEvaluationForm)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_phase_detail_id' => 'required|exists:form_phase_details,id',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.id' => 'nullable|exists:review_form_fields,id',
            'fields.*.field_type_id' => 'required|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.validation_rules' => 'nullable|array',
            'fields.*.options' => 'array',
            'fields.*.options.*.id' => 'nullable|exists:review_form_field_options,id',
            'fields.*.options.*.label' => 'required|string|max:255',
            'fields.*.options.*.value' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Update evaluation form
            $reviewEvaluationForm->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'form_phase_detail_id' => $validated['form_phase_detail_id'],
                'is_required' => $validated['is_required'] ?? true,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Handle fields
            $existingFieldIds = collect($validated['fields'] ?? [])
                ->pluck('id')
                ->filter()
                ->toArray();

            // Delete fields not in the request
            $reviewEvaluationForm->reviewFormFields()
                ->whereNotIn('id', $existingFieldIds)
                ->delete();

            // Update or create fields
            if (isset($validated['fields'])) {
                foreach ($validated['fields'] as $index => $fieldData) {
                    $field = isset($fieldData['id'])
                        ? ReviewFormField::find($fieldData['id'])
                        : new ReviewFormField();

                    $field->review_evaluation_form_id = $reviewEvaluationForm->id;
                    $field->field_type_id = $fieldData['field_type_id'];
                    $field->label = $fieldData['label'];
                    $field->description = $fieldData['description'] ?? null;
                    $field->is_required = $fieldData['is_required'] ?? false;
                    $field->order = $index + 1;
                    $field->validation_rules = $fieldData['validation_rules'] ?? null;
                    $field->save();

                    // Handle options
                    if (isset($fieldData['options'])) {
                        $existingOptionIds = collect($fieldData['options'])
                            ->pluck('id')
                            ->filter()
                            ->toArray();

                        $field->reviewFormFieldOptions()
                            ->whereNotIn('id', $existingOptionIds)
                            ->delete();

                        foreach ($fieldData['options'] as $optionIndex => $optionData) {
                            $option = isset($optionData['id'])
                                ? ReviewFormFieldOption::find($optionData['id'])
                                : new ReviewFormFieldOption();

                            $option->review_form_field_id = $field->id;
                            $option->label = $optionData['label'];
                            $option->value = $optionData['value'] ?? null;
                            $option->order = $optionIndex + 1;
                            $option->save();
                        }
                    } else {
                        $field->reviewFormFieldOptions()->delete();
                    }
                }
            }

            DB::commit();

            return redirect()->back()
                ->with('success', 'Review evaluation form updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to update review evaluation form: ' . $e->getMessage()]);
        }
    }

    public function destroy(ReviewEvaluationForm $reviewEvaluationForm)
    {
        // Check if form is being used
        $assignmentCount = $reviewEvaluationForm->reviewerFormAssignments()->count();

        if ($assignmentCount > 0) {
            return back()->withErrors([
                'error' => "Cannot delete this evaluation form. It is currently assigned to {$assignmentCount} reviewer(s)."
            ]);
        }

        try {
            DB::beginTransaction();

            // Delete all related data
            foreach ($reviewEvaluationForm->reviewFormFields as $field) {
                $field->reviewFormFieldOptions()->delete();
            }
            $reviewEvaluationForm->reviewFormFields()->delete();
            $reviewEvaluationForm->delete();

            DB::commit();

            return redirect()->route('admin.review-evaluation-forms.index')
                ->with('success', 'Review evaluation form deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to delete review evaluation form: ' . $e->getMessage()]);
        }
    }

    public function duplicate(ReviewEvaluationForm $reviewEvaluationForm)
    {
        try {
            DB::beginTransaction();

            $newForm = $reviewEvaluationForm->replicate();
            $newForm->title = $reviewEvaluationForm->title . ' (Copy)';
            $newForm->order = ReviewEvaluationForm::where('form_phase_detail_id', $reviewEvaluationForm->form_phase_detail_id)
                ->max('order') + 1;
            $newForm->save();

            // Duplicate fields
            foreach ($reviewEvaluationForm->reviewFormFields as $field) {
                $newField = $field->replicate();
                $newField->review_evaluation_form_id = $newForm->id;
                $newField->save();

                // Duplicate options
                foreach ($field->reviewFormFieldOptions as $option) {
                    $newOption = $option->replicate();
                    $newOption->review_form_field_id = $newField->id;
                    $newOption->save();
                }
            }

            DB::commit();

            return redirect()->route('admin.review-evaluation-forms.edit', $newForm)
                ->with('success', 'Review evaluation form duplicated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to duplicate review evaluation form: ' . $e->getMessage()]);
        }
    }

    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:review_evaluation_forms,id',
            'items.*.order' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['items'] as $item) {
                ReviewEvaluationForm::where('id', $item['id'])
                    ->update(['order' => $item['order']]);
            }

            DB::commit();

            return response()->json(['message' => 'Order updated successfully.']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Failed to update order.'], 500);
        }
    }

    public function preview(ReviewEvaluationForm $reviewEvaluationForm)
    {
        $reviewEvaluationForm->load([
            'reviewFormFields' => function ($query) {
                $query->ordered()->with([
                    'fieldType:id,name',
                    'reviewFormFieldOptions' => function ($q) {
                        $q->ordered();
                    }
                ]);
            }
        ]);

        return Inertia::render('ReviewEvaluationForms/Preview', [
            'evaluationForm' => $reviewEvaluationForm
        ]);
    }
}
