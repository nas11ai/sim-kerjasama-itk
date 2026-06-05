<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormFieldResponse;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionPeriod;
use App\Services\EmailNotificationService;
use App\SubmissionStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserFormController extends Controller
{
    protected $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function dashboard()
    {
        $user = Auth::user();

        // Get user's study program and role
        $studyProgram = $user->studyProgram ?? null;
        $userRoles = $user->getRoleNames();
        $primaryRole = $userRoles->first() ?? 'user';

        // Check if user is a reviewer
        $reviewer = Reviewer::where('user_id', $user->id)->first();
        $isReviewer = $reviewer !== null;

        // Get submission periods with accessible form phases
        $submissionPeriods = SubmissionPeriod::with([
            'submissionDates.submissionDateLabel',
            'submissionPeriodPhases.formPhase.formPhaseDetails' => function ($query) use ($user, $studyProgram) {
                $query->whereHas('formAccessControl', function ($q) use ($user, $studyProgram) {
                    $q->whereHas('role', function ($roleQuery) use ($user) {
                        $roleQuery->whereIn('name', $user->getRoleNames());
                    });

                    if ($studyProgram) {
                        $q->where('study_program_id', $studyProgram->id);
                    }
                })
                    ->with(['formAccessControl.form.formFields', 'phaseType'])
                    ->orderBy('order');
            },
        ])
            ->get()
            ->map(function ($period) use ($user, $studyProgram) {
                // Fix: Use correct attribute name based on your model
                $dates = $period->submissionDates->sortBy('datetime'); // Changed from 'datetime' to 'date'
                $now = Carbon::now();

                // Determine period status and activity
                if ($dates->isEmpty()) {
                    $period->is_active = false;
                    $period->status = 'no_dates';
                    $period->start_date = null;
                    $period->end_date = null;
                } else {
                    // Use 'date' attribute consistently
                    $startDate = Carbon::parse($dates->first()->datetime);
                    $endDate = Carbon::parse($dates->last()->datetime);

                    if ($now->lt($startDate)) {
                        $period->is_active = false;
                        $period->status = 'upcoming';
                    } elseif ($now->between($startDate, $endDate)) {
                        $period->is_active = true;
                        $period->status = 'active';
                    } else {
                        $period->is_active = false;
                        $period->status = 'expired';
                    }

                    $period->start_date = $dates->first()->datetime;
                    $period->end_date = $dates->last()->datetime;
                }

                // Process form phases with user progress
                $period->form_phases = $period->submissionPeriodPhases->map(function ($periodPhase) use ($user, $studyProgram) {
                    $formPhase = $periodPhase->formPhase;

                    // Get user's accessible form access controls
                    // Filter by role AND study_program_id to avoid counting forms multiple times
                    $accessibleForms = $formPhase->formPhaseDetails->filter(function ($detail) use ($user, $studyProgram) {
                        $formAccessControl = $detail->formAccessControl;

                        if (!$formAccessControl || !$formAccessControl->role) {
                            return false;
                        }

                        // Check role match
                        if (!$user->hasRole($formAccessControl->role->name)) {
                            return false;
                        }

                        // Check study program match (if user has study program)
                        if ($studyProgram && $formAccessControl->study_program_id !== $studyProgram->id) {
                            return false;
                        }

                        return true;
                    });

                    // Calculate progress
                    $totalForms = $accessibleForms->count();
                    $completedForms = 0;
                    $pendingReview = 0;
                    $canProceed = true;

                    foreach ($accessibleForms as $detail) {
                        $submission = FormSubmission::where('form_id', $detail->formAccessControl->form_id)
                            ->where('submitted_by', $user->id)
                            ->first();

                        if ($submission && $submission->is_submitted) {
                            $completedForms++;
                            if ($detail->needs_review && !$submission->canProceed()) {
                                $pendingReview++;
                                $canProceed = false;
                            }
                        }
                    }

                    return [
                        'id' => $formPhase->id,
                        'title' => $formPhase->title,
                        'description' => $formPhase->description,
                        'user_can_access' => $totalForms > 0,
                        'user_progress' => [
                            'total_forms' => $totalForms,
                            'completed_forms' => $completedForms,
                            'pending_review' => $pendingReview,
                            'can_proceed' => $canProceed,
                        ],
                    ];
                })->filter(function ($phase) {
                    return $phase['user_can_access'];
                })->values();

                return $period;
            });

        // Get review stats if user is reviewer
        $reviewStats = null;
        if ($isReviewer) {
            $reviewStats = [
                'total_assigned' => ReviewSummary::where('reviewer_id', $reviewer->id)->count(),
                'pending_reviews' => ReviewSummary::where('reviewer_id', $reviewer->id)
                    ->where('status', 'open')->count(),
                'completed_reviews' => ReviewSummary::where('reviewer_id', $reviewer->id)
                    ->where('status', 'resolved')->count(),
                'rejected_reviews' => ReviewSummary::where('reviewer_id', $reviewer->id)
                    ->where('status', 'closed')->count(),
            ];
        }

        return Inertia::render('User/DashboardPage', [
            'submissionPeriods' => $submissionPeriods,
            'userRole' => $primaryRole,
            'studyProgram' => $studyProgram ? [
                'id' => $studyProgram->id,
                'name' => $studyProgram->name,
                'faculty' => [
                    'name' => $studyProgram->faculty->name,
                ],
            ] : null,
            'isReviewer' => $isReviewer,
            'reviewStats' => $reviewStats,
            'reviewer' => $reviewer ? [
                'id' => $reviewer->id,
                'reviewer_role' => $reviewer->reviewerRole->name,
            ] : null,
        ]);
    }

    // Add method untuk reviewer submissions
    public function reviewerSubmissions(Request $request)
    {
        $user = Auth::user();
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer');
        }

        $query = FormSubmission::whereHas('reviewSummaries', function ($q) use ($reviewer) {
            $q->where('reviewer_id', $reviewer->id);
        })
            ->with([
                'form:id,title',
                'submittedBy:id,name,email',
                'reviewSummaries' => function ($q) use ($reviewer) {
                    $q->where('reviewer_id', $reviewer->id);
                },
            ]);

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->whereHas('reviewSummaries', function ($q) use ($reviewer, $request) {
                $q->where('reviewer_id', $reviewer->id)
                    ->where('status', $request->status);
            });
        }

        // Search by submitter name or form title
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('submittedBy', function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('form', function ($query) use ($search) {
                        $query->where('title', 'ilike', "%{$search}%");
                    });
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Reviewer/Submissions/IndexPage', [
            'submissions' => $submissions,
            'filters' => $request->only(['status', 'search']),
            'reviewer' => $reviewer->load('reviewerRole'),
        ]);
    }

    public function showFormPhase(SubmissionPeriod $period, FormPhase $phase, Request $request)
    {
        $user = Auth::user();
        $studyProgram = $user->studyProgram ?? null;

        // Get form access controls for this phase that user can access
        $formAccessControls = $phase->formPhaseDetails()
            ->whereHas('formAccessControl', function ($query) use ($user, $studyProgram) {
                $query->whereHas('role', function ($roleQuery) use ($user) {
                    $roleQuery->whereIn('name', $user->getRoleNames());
                });

                if ($studyProgram) {
                    $query->where('study_program_id', $studyProgram->id);
                }
            })
            ->with([
                'formAccessControl.form.formFields',
                'formAccessControl.form.formFields.fieldType',
                'formAccessControl.form.formFields.formFieldOptions',
                'phaseType',
            ])
            ->orderBy('order')
            ->get()
            ->map(function ($detail) use ($user) {
                $formAccessControl = $detail->formAccessControl;

                // Get user's submission for this form with responses
                $submission = FormSubmission::with('formFieldResponses')
                    ->where('form_id', $formAccessControl->form_id)
                    ->where('submitted_by', $user->id)
                    ->first();

                return [
                    'id' => $formAccessControl->id,
                    'form' => $formAccessControl->form,
                    'order' => $detail->order,
                    'needs_review' => $detail->needs_review,
                    'phase_type' => $detail->phaseType,
                    'user_submission' => $submission ? [
                        'id' => $submission->id,
                        'is_submitted' => $submission->is_submitted,
                        'can_proceed' => $submission->canProceed(),
                        'created_at' => $submission->created_at->toISOString(),
                        'updated_at' => $submission->updated_at->toISOString(),
                        'responses' => $submission->formFieldResponses->mapWithKeys(function ($response) {
                            return ["field_{$response->form_field_id}" => $response->value];
                        }),
                    ] : null,
                ];
            });

        if ($formAccessControls->isEmpty()) {
            return redirect()->route('user.dashboard')
                ->with('error', 'Anda tidak memiliki akses ke tahap formulir ini.');
        }

        return Inertia::render('User/FormPhase', [
            'submissionPeriod' => [
                'id' => $period->id,
                'name' => $period->name,
            ],
            'formPhase' => [
                'id' => $phase->id,
                'title' => $phase->title,
                'description' => $phase->description,
                'form_access_controls' => $formAccessControls,
            ],
            'currentStep' => $request->get('step', 1),
        ]);
    }

    public function saveDraft(Request $request)
    {
        $user = Auth::user();

        // Enhanced validation to handle files
        $validated = $request->validate([
            'form_id' => 'required|exists:forms,id',
            'submission_period_id' => 'required|exists:submission_periods,id',
            'form_phase_id' => 'required|exists:form_phases,id',
            'responses' => 'required|array',
            'responses.*.form_field_id' => 'required|exists:form_fields,id',
            'responses.*.value' => 'nullable',
        ]);

        // Handle file uploads
        $fileUploads = [];
        if ($request->hasFile('responses')) {
            foreach ($request->file('responses') as $key => $fileData) {
                if (isset($fileData['value']) && $fileData['value']) {
                    $file = $fileData['value'];
                    $path = $file->store('form-uploads', 'public');
                    $fileUploads[$key] = $path;
                }
            }
        }

        DB::transaction(function () use ($validated, $user, $fileUploads) {
            // Find or create form submission
            $submission = FormSubmission::firstOrCreate([
                'form_id' => $validated['form_id'],
                'submitted_by' => $user->id,
            ], [
                'is_submitted' => false,
            ]);

            // Delete existing responses
            $submission->formFieldResponses()->delete();

            // Save new responses
            foreach ($validated['responses'] as $index => $responseData) {
                $value = $responseData['value'];

                // Use file path if file was uploaded
                if (isset($fileUploads[$index])) {
                    $value = $fileUploads[$index];
                }

                // Save response if not empty
                if (!empty($value) || $value === '0' || $value === 0) {
                    FormFieldResponse::create([
                        'form_submission_id' => $submission->id,
                        'form_field_id' => $responseData['form_field_id'],
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                    ]);
                }
            }
        });

        return redirect()->back()
            ->with('success', 'Draft berhasil disimpan.');
    }

    public function submitForm(Request $request)
    {
        $user = Auth::user();

        // Enhanced validation to handle files
        $validated = $request->validate([
            'form_id' => 'required|exists:forms,id',
            'submission_period_id' => 'required|exists:submission_periods,id',
            'form_phase_id' => 'required|exists:form_phases,id',
            'responses' => 'required|array',
            'responses.*.form_field_id' => 'required|exists:form_fields,id',
            'responses.*.value' => 'nullable',
        ]);

        // Handle file uploads
        $fileUploads = [];
        if ($request->hasFile('responses')) {
            foreach ($request->file('responses') as $key => $fileData) {
                if (isset($fileData['value']) && $fileData['value']) {
                    $file = $fileData['value'];

                    // Validate file size (max 10MB)
                    if ($file->getSize() > 10 * 1024 * 1024) {
                        return redirect()->back()
                            ->withErrors(['file' => 'Ukuran file terlalu besar. Maksimal 10MB.'])
                            ->with('error', 'Upload file gagal.');
                    }

                    $path = $file->store('form-uploads', 'public');
                    $fileUploads[$key] = $path;
                }
            }
        }

        // Validate that all required fields are filled
        $form = Form::with([
            'formFields' => function ($query) {
                $query->where('is_required', true);
            },
        ])->find($validated['form_id']);

        $requiredFields = $form->formFields;
        $responseValues = collect($validated['responses'])->keyBy('form_field_id');

        foreach ($requiredFields as $field) {
            $response = $responseValues->get($field->id);
            $value = $response ? $response['value'] : null;

            // Check if file upload is required and uploaded
            if ($field->fieldType->name === 'file') {
                $hasFile = false;
                foreach ($fileUploads as $key => $filePath) {
                    if ($validated['responses'][$key]['form_field_id'] == $field->id) {
                        $hasFile = true;
                        break;
                    }
                }
                if (!$hasFile) {
                    return redirect()->back()
                        ->withErrors(['field_'.$field->id => "Field '{$field->label}' wajib diisi."])
                        ->with('error', 'Silakan lengkapi semua field yang wajib diisi.');
                }
            } else {
                // Regular field validation
                if (!$response || (empty(trim($value)) && $value !== '0' && $value !== 0)) {
                    return redirect()->back()
                        ->withErrors(['field_'.$field->id => "Field '{$field->label}' wajib diisi."])
                        ->with('error', 'Silakan lengkapi semua field yang wajib diisi.');
                }
            }
        }

        DB::transaction(function () use ($validated, $user, $fileUploads) {
            // Find or create form submission
            $submission = FormSubmission::firstOrCreate([
                'form_id' => $validated['form_id'],
                'submitted_by' => $user->id,
            ], [
                'is_submitted' => false,
            ]);

            // Update submission status
            $submission->update([
                'is_submitted' => true,
            ]);

            // Delete existing responses
            $submission->formFieldResponses()->delete();

            // Save new responses
            foreach ($validated['responses'] as $index => $responseData) {
                $value = $responseData['value'];

                // Use file path if file was uploaded
                if (isset($fileUploads[$index])) {
                    $value = $fileUploads[$index];
                }

                // Save response if not empty
                if (!empty($value) || $value === '0' || $value === 0) {
                    FormFieldResponse::create([
                        'form_submission_id' => $submission->id,
                        'form_field_id' => $responseData['form_field_id'],
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                    ]);
                }
            }

            // Check if this form needs review
            $formPhaseDetail = FormPhaseDetail::where('form_phase_id', $validated['form_phase_id'])
                ->whereHas('formAccessControl', function ($query) use ($validated, $user) {
                    $query->where('form_id', $validated['form_id'])
                        ->whereHas('role', function ($roleQuery) use ($user) {
                            $roleQuery->whereIn('name', $user->getRoleNames());
                        });
                })
                ->first();

            if ($formPhaseDetail && !$formPhaseDetail->needs_review) {
                $submission->update(['status' => SubmissionStatus::APPROVED]);

                // TODO: Create review request or notification
                // You can implement notification system here
            } else {
                $submission->update(['status' => SubmissionStatus::PENDING]);
            }

            $this->emailService->notifyAdminFormSubmission($submission);
        });

        return redirect()->back()
            ->with('success', 'Formulir berhasil diserahkan.');
    }

    public function getFormSubmissionData(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'form_id' => 'required|exists:forms,id',
            'form_submission_id' => 'nullable|exists:form_submissions,id',
        ]);

        $submission = null;

        if ($validated['form_submission_id']) {
            $submission = FormSubmission::with('formFieldResponses')
                ->where('id', $validated['form_submission_id'])
                ->where('submitted_by', $user->id)
                ->first();
        } else {
            $submission = FormSubmission::with('formFieldResponses')
                ->where('form_id', $validated['form_id'])
                ->where('submitted_by', $user->id)
                ->first();
        }

        if (!$submission) {
            return response()->json(['responses' => []]);
        }

        $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
            // Handle JSON values (for multi-select or complex data)
            $value = $response->value;
            if (json_decode($value) !== null) {
                $value = json_decode($value);
            }

            return ["field_{$response->form_field_id}" => $value];
        });

        return response()->json(['responses' => $responses]);
    }
}
