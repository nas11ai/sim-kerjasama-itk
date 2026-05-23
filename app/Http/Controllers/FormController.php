<?php

namespace App\Http\Controllers;

use App\Models\FieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $perPage = $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $formTypeFilter = $request->get('form_type');
        $isActiveFilter = $request->get('is_active');

        $query = Form::with(['formType', 'formFields']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('formType', function ($typeQuery) use ($search) {
                        $typeQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($formTypeFilter && $formTypeFilter !== 'all') {
            $query->where('form_type_id', $formTypeFilter);
        }

        if ($isActiveFilter && $isActiveFilter !== 'all') {
            $query->where('is_active', $isActiveFilter === 'active' ? 1 : 0);
        }

        if ($sortBy === 'form_type') {
            $query->join('form_types', 'forms.form_type_id', '=', 'form_types.id')
                ->select('forms.*')
                ->orderBy('form_types.name', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $forms = $query->paginate($perPage)->withQueryString();

        $formTypes = FormType::orderBy('name')->get();

        return Inertia::render('Forms/Index', [
            'forms' => $forms,
            'formTypes' => $formTypes,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'form_type' => $formTypeFilter,
                'is_active' => $isActiveFilter,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Forms/Create', [
            'formTypes' => FormType::orderBy('name')->get(),
            'fieldTypes' => FieldType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type_id' => 'required|exists:form_types,id',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.field_type_id' => 'required|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.is_required' => 'boolean',
            'fields.*.options' => 'array',
            'fields.*.options.*.label' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $form = Form::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'form_type_id' => $validated['form_type_id'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (isset($validated['fields'])) {
                foreach ($validated['fields'] as $index => $fieldData) {
                    $field = FormField::create([
                        'form_id' => $form->id,
                        'field_type_id' => $fieldData['field_type_id'],
                        'label' => $fieldData['label'],
                        'is_required' => $fieldData['is_required'] ?? false,
                        'order' => $index + 1,
                    ]);

                    if (isset($fieldData['options'])) {
                        foreach ($fieldData['options'] as $optionIndex => $optionData) {
                            FormFieldOption::create([
                                'form_field_id' => $field->id,
                                'label' => $optionData['label'],
                                'order' => $optionIndex + 1,
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil dibuat!');
    }

    public function show(Form $form)
    {
        $form->load(['formType', 'formFields.fieldType', 'formFields.formFieldOptions']);

        return Inertia::render('Forms/Show', [
            'form' => $form,
        ]);
    }

    public function edit(Form $form)
    {
        $form->load(['formType', 'formFields.fieldType', 'formFields.formFieldOptions']);

        return Inertia::render('Forms/Edit', [
            'form' => $form,
            'formTypes' => FormType::orderBy('name')->get(),
            'fieldTypes' => FieldType::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Form $form)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type_id' => 'required|exists:form_types,id',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.id' => 'nullable|exists:form_fields,id',
            'fields.*.field_type_id' => 'required|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.is_required' => 'boolean',
            'fields.*.options' => 'array',
            'fields.*.options.*.id' => 'nullable|exists:form_field_options,id',
            'fields.*.options.*.label' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $form) {
            $form->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'form_type_id' => $validated['form_type_id'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Delete existing fields that are not in the request
            $existingFieldIds = collect($validated['fields'] ?? [])
                ->pluck('id')
                ->filter()
                ->toArray();

            $form->formFields()
                ->whereNotIn('id', $existingFieldIds)
                ->delete();

            // Update or create fields
            if (isset($validated['fields'])) {
                foreach ($validated['fields'] as $index => $fieldData) {
                    $field = isset($fieldData['id'])
                        ? FormField::find($fieldData['id'])
                        : new FormField;

                    $field->form_id = $form->id;
                    $field->field_type_id = $fieldData['field_type_id'];
                    $field->label = $fieldData['label'];
                    $field->is_required = $fieldData['is_required'] ?? false;
                    $field->order = $index + 1;
                    $field->save();

                    // Handle options
                    if (isset($fieldData['options'])) {
                        $existingOptionIds = collect($fieldData['options'])
                            ->pluck('id')
                            ->filter()
                            ->toArray();

                        $field->formFieldOptions()
                            ->whereNotIn('id', $existingOptionIds)
                            ->delete();

                        foreach ($fieldData['options'] as $optionIndex => $optionData) {
                            $option = isset($optionData['id'])
                                ? FormFieldOption::find($optionData['id'])
                                : new FormFieldOption;

                            $option->form_field_id = $field->id;
                            $option->label = $optionData['label'];
                            $option->order = $optionIndex + 1;
                            $option->save();
                        }
                    } else {
                        $field->formFieldOptions()->delete();
                    }
                }
            }
        });

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil diperbarui!');
    }

    public function destroy(Form $form)
    {
        $form->delete();

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil dihapus!');
    }

    public function duplicate(Form $form)
    {
        DB::transaction(function () use ($form) {
            $newForm = $form->replicate();
            $newForm->title = $form->title.' (Copy)';
            $newForm->save();

            foreach ($form->formFields as $field) {
                $newField = $field->replicate();
                $newField->form_id = $newForm->id;
                $newField->save();

                foreach ($field->formFieldOptions as $option) {
                    $newOption = $option->replicate();
                    $newOption->form_field_id = $newField->id;
                    $newOption->save();
                }
            }
        });

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir berhasil diduplikasi!');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);

        Form::whereIn('id', $ids)->delete();

        return redirect()->route('admin.forms.index')
            ->with('success', 'Formulir terpilih berhasil dihapus!');
    }
}
