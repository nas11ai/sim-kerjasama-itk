<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormFieldOption;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BiodataController extends Controller
{
    public function showBiodataForm()
    {
        $user = Auth::user();
        $studyProgram = $user->studyProgram ?? null;

        $biodataForm = Form::where('form_type_id', 1)
            ->where('is_active', true)
            ->whereHas('formAccessControls', function ($q) use ($user, $studyProgram) {
                $q->whereHas('role', fn ($r) => $r->whereIn('name', $user->getRoleNames()))
                    ->when(
                        $studyProgram,
                        fn ($r) => $r->where(function ($sub) use ($studyProgram) {
                            $sub->whereNull('study_program_id')
                                ->orWhere('study_program_id', $studyProgram->id);
                        })
                    );
            })
            ->with([
                'formFields' => function ($query) {
                    $query->orderBy('order');
                },
                'formFields.fieldType',
                'formFields.formFieldOptions',
            ])
            ->first();

        if (!$biodataForm) {
            return redirect()->route('user.dashboard')
                ->with('error', 'Form biodata tidak ditemukan atau tidak aktif.');
        }

        $hasAccess = $biodataForm->formAccessControls()
            ->whereHas('role', fn ($q) => $q->whereIn('name', $user->getRoleNames()))
            ->when($studyProgram, fn ($q) => $q->where('study_program_id', $studyProgram->id))
            ->exists();

        if (!$hasAccess) {
            return redirect()->route('user.dashboard')
                ->with('error', 'Anda tidak memiliki akses ke form biodata ini.');
        }

        $submission = FormSubmission::with('formFieldResponses')
            ->where('form_id', $biodataForm->id)
            ->where('submitted_by', $user->id)
            ->latest()
            ->first();

        $existingResponses = $submission
            ? $submission->formFieldResponses->mapWithKeys(fn ($r) => [$r->form_field_id => $r->value])->toArray()
            : [];

        $status = $submission?->status;

        if ($submission->status instanceof \BackedEnum) {
            $status = $submission->status->value;
        }

        $canEdit = !$submission || in_array($status, ['rejected', 'needs_revision']);

        return Inertia::render('User/Biodata/IndexPage', [
            'form' => [
                'id' => $biodataForm->id,
                'title' => $biodataForm->title,
                'description' => $biodataForm->description,
                'form_fields' => $biodataForm->formFields->map(fn ($field) => [
                    'id' => $field->id,
                    'label' => $field->label,
                    'is_required' => $field->is_required,
                    'order' => $field->order,
                    'helper_text' => $field->helper_text,
                    'field_type' => [
                        'id' => $field->fieldType->id,
                        'name' => $field->fieldType->name,
                    ],
                    'form_field_options' => $field->formFieldOptions
                        ->map(function (FormFieldOption $option): array {
                            return [
                                'id' => $option->id,
                                'label' => $option->label,
                                'value' => $option->value,
                            ];
                        }),
                ]),

            ],
            'existingSubmission' => $submission ? [
                'id' => $submission->id,
                'is_submitted' => $submission->is_submitted,
                'status' => $status,
                'created_at' => $submission->created_at->toISOString(),
                'updated_at' => $submission->updated_at->toISOString(),
            ] : null,
            'existingResponses' => $existingResponses,
            'canEdit' => $canEdit,
            'biodataStatus' => session('biodataStatus'),
        ]);
    }

    public function submitForm(Request $request)
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'form_id' => 'required|exists:forms,id',
                'is_submitted' => 'required|boolean',
                'responses' => 'required|array',
                'responses.*.form_field_id' => 'required|exists:form_fields,id',
                'responses.*.value' => 'nullable',
            ]);

            $fileUploads = [];
            if ($request->hasFile('file_uploads')) {
                foreach ($request->file('file_uploads') as $fieldId => $file) {
                    if ($file && $file->isValid()) {
                        // file size max 10MB
                        if ($file->getSize() > 10 * 1024 * 1024) {
                            return back()
                                ->withErrors(['file_'.$fieldId => 'Ukuran file terlalu besar. Maksimal 10MB.'])
                                ->with('error', 'Upload file gagal: File terlalu besar.')
                                ->withInput();
                        }

                        $path = $file->store('biodata-uploads', 'public');
                        $fileUploads[$fieldId] = $path;
                    }
                }
            }

            // form with required fields
            $form = Form::with([
                'formFields' => function ($query) {
                    $query->where('is_required', true);
                },
            ])->find($validated['form_id']);

            // if is_submitted = true
            if ($validated['is_submitted']) {
                $requiredFields = $form->formFields;
                $responseValues = collect($validated['responses'])->keyBy('form_field_id');

                foreach ($requiredFields as $field) {
                    $response = $responseValues->get($field->id);
                    $value = $response ? $response['value'] : null;

                    if ($field->fieldType->name === 'file') {
                        $hasFile = isset($fileUploads[$field->id]) ||
                            (!empty($value) && filter_var($value, FILTER_VALIDATE_URL) === false);

                        if (!$hasFile) {

                            return back()
                                ->withErrors(['field_'.$field->id => "Field '{$field->label}' wajib diisi."])
                                ->with('error', 'Silakan lengkapi semua field yang wajib diisi.')
                                ->withInput();
                        }
                    } else {
                        if (blank($value)) {
                            return back()
                                ->withErrors(['field_'.$field->id => "Field '{$field->label}' wajib diisi."])
                                ->with('error', 'Silakan lengkapi semua field yang wajib diisi.')
                                ->withInput();
                        }
                    }
                }
            }

            DB::beginTransaction();

            try {
                // updateOrCreate submission
                $submission = FormSubmission::updateOrCreate(
                    [
                        'form_id' => $validated['form_id'],
                        'submitted_by' => $user->id,
                    ],
                    [
                        'is_submitted' => $validated['is_submitted'],
                        'submitted_at' => $validated['is_submitted'] ? now() : null,
                        'status' => $validated['is_submitted'] ? 'pending' : null,
                    ]
                );

                $submission->formFieldResponses()->delete();

                foreach ($validated['responses'] as $responseData) {
                    $fieldId = $responseData['form_field_id'];
                    $value = $responseData['value'];

                    if (isset($fileUploads[$fieldId])) {
                        $value = $fileUploads[$fieldId];
                    }

                    if (!empty($value) || $value === '0' || $value === 0) {
                        FormFieldResponse::create([
                            'form_submission_id' => $submission->id,
                            'form_field_id' => $fieldId,
                            'value' => is_array($value) ? json_encode($value) : (string) $value,
                        ]);
                    }
                }

                DB::commit();

                $message = $validated['is_submitted']
                    ? 'Biodata berhasil diserahkan dan menunggu persetujuan.'
                    : null;

                return redirect()
                    ->route('user.dashboard')
                    ->with('success', $message);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->with('error', 'Validasi gagal. Silakan periksa input Anda.')
                ->withInput();
        } catch (Exception $e) {
            return back()
                ->with('error', 'Terjadi kesalahan saat menyimpan biodata. Silakan coba lagi.')
                ->withInput();
        }
    }
}
