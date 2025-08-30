<?php

namespace App\Http\Controllers;

use App\Models\SubmissionPeriod;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\FormFieldResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubmissionViewController extends Controller
{
    public function userIndex(Request $request)
    {
        $user = Auth::user();
        $studyProgram = $user->studyProgram;

        // Get submission periods with user's accessible form phases
        $submissionPeriods = SubmissionPeriod::with([
            'submissionDates.submissionDateLabel',
            'submissionPeriodPhases.formPhase' => function ($query) use ($user, $studyProgram) {
                $query->whereHas('formPhaseDetails.formAccessControl', function ($q) use ($user, $studyProgram) {
                    $q->whereHas('role', function ($roleQuery) use ($user) {
                        $roleQuery->whereIn('name', $user->getRoleNames());
                    });
                    if ($studyProgram) {
                        $q->where('study_program_id', $studyProgram->id);
                    }
                });
            }
        ])
            ->whereHas('submissionPeriodPhases.formPhase.formPhaseDetails.formAccessControl', function ($query) use ($user, $studyProgram) {
                $query->whereHas('role', function ($roleQuery) use ($user) {
                    $roleQuery->whereIn('name', $user->getRoleNames());
                });
                if ($studyProgram) {
                    $query->where('study_program_id', $studyProgram->id);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($period) use ($user) {
                // Get user's submissions for this period
                $userSubmissions = FormSubmission::with(['form.formType'])
                    ->where('submitted_by', $user->id)
                    ->whereHas('form.formAccessControls.formPhaseDetails', function ($query) use ($period) {
                    $query->whereHas('formPhase.submissionPeriodPhases', function ($q) use ($period) {
                        $q->where('submission_period_id', $period->id);
                    });
                })
                    ->get();

                $period->user_submissions_count = $userSubmissions->count();
                $period->user_draft_count = $userSubmissions->where('is_submitted', false)->count();
                $period->user_submitted_count = $userSubmissions->where('is_submitted', true)->count();

                return $period;
            });

        return Inertia::render('User/Submissions/Index', [
            'submissionPeriods' => $submissionPeriods,
            'userRole' => $user->getRoleNames()->first(),
            'studyProgram' => $studyProgram ? [
                'id' => $studyProgram->id,
                'name' => $studyProgram->name,
                'faculty' => ['name' => $studyProgram->faculty->name]
            ] : null
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = SubmissionPeriod::with([
            'submissionDates.submissionDateLabel',
            'submissionPeriodPhases.formPhase'
        ]);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $submissionPeriods = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($period) {
                // Get submission statistics for this period
                $submissionStats = FormSubmission::with([
                    'form',
                    'form.formType',
                    'form.formAccessControls',
                    'form.formAccessControls.formPhaseDetails',
                ])->where('is_submitted', true)
                    ->whereHas('form.formAccessControls.formPhaseDetails', function ($query) use ($period) {
                    $query->whereHas('formPhase.submissionPeriodPhases', function ($q) use ($period) {
                        $q->where('submission_period_id', $period->id);
                    });
                })
                    ->selectRaw('
                        count(*) as total_submissions,
                        count(case when can_proceed = true then 1 end) as approved_submissions,
                        count(case when can_proceed = false then 1 end) as pending_review
                    ')
                    ->first();

                $period->total_submissions = $submissionStats->total_submissions ?? 0;
                $period->approved_submissions = $submissionStats->approved_submissions ?? 0;
                $period->pending_review = $submissionStats->pending_review ?? 0;

                return $period;
            });

        return Inertia::render('Submissions/Index', [
            'submissionPeriods' => $submissionPeriods,
            'filters' => $request->only(['search'])
        ]);
    }

    public function userShowPeriod(SubmissionPeriod $period)
    {
        $user = Auth::user();
        $studyProgram = $user->studyProgram;

        // Get form phases for this period that user can access
        $formPhases = FormPhase::whereHas('submissionPeriodPhases', function ($query) use ($period) {
            $query->where('submission_period_id', $period->id);
        })
            ->whereHas('formPhaseDetails.formAccessControl', function ($query) use ($user, $studyProgram) {
                $query->whereHas('role', function ($roleQuery) use ($user) {
                    $roleQuery->whereIn('name', $user->getRoleNames());
                });
                if ($studyProgram) {
                    $query->where('study_program_id', $studyProgram->id);
                }
            })
            ->with([
                'formPhaseDetails' => function ($query) use ($user, $studyProgram) {
                    $query->whereHas('formAccessControl', function ($q) use ($user, $studyProgram) {
                        $q->whereHas('role', function ($roleQuery) use ($user) {
                            $roleQuery->whereIn('name', $user->getRoleNames());
                        });
                        if ($studyProgram) {
                            $q->where('study_program_id', $studyProgram->id);
                        }
                    })
                        ->with(['formAccessControl.form.formType'])
                        ->orderBy('order');
                }
            ])
            ->get()
            ->map(function ($phase) use ($user) {
                // Use a temporary variable instead of direct assignment
                $userSubmissions = [];

                foreach ($phase->formPhaseDetails as $detail) {
                    $submission = FormSubmission::where('form_id', $detail->formAccessControl->form_id)
                        ->where('submitted_by', $user->id)
                        ->first();

                    if ($submission) {
                        $userSubmissions[] = [
                            'id' => $submission->id,
                            'form' => $detail->formAccessControl->form,
                            'is_submitted' => $submission->is_submitted,
                            'can_proceed' => $submission->can_proceed,
                            'submitted_at' => $submission->submitted_at,
                            'created_at' => $submission->created_at,
                            'updated_at' => $submission->updated_at,
                        ];
                    }
                }

                // Set the attribute using setAttribute or direct property assignment
                $phase->setAttribute('user_submissions', $userSubmissions);

                return $phase;
            });

        return Inertia::render('User/Submissions/ShowPeriod', [
            'submissionPeriod' => $period->load('submissionDates.submissionDateLabel'),
            'formPhases' => $formPhases
        ]);
    }

    public function adminShowPeriod(SubmissionPeriod $period, Request $request)
    {
        // Get form phases for this period
        $formPhases = FormPhase::whereHas('submissionPeriodPhases', function ($query) use ($period) {
            $query->where('submission_period_id', $period->id);
        })
            ->with(['formPhaseDetails.formAccessControl.form.formType'])
            ->get();

        // Get submissions for this period with filters
        $query = FormSubmission::with([
            'form.formType',
            'submittedBy:id,name,email,study_program_id',
            'submittedBy.studyProgram.faculty'
        ])
            ->where('is_submitted', true)
            ->whereHas('form.formAccessControls.formPhaseDetails', function ($q) use ($period) {
                $q->whereHas('formPhase.submissionPeriodPhases', function ($query) use ($period) {
                    $query->where('submission_period_id', $period->id);
                });
            });

        // Apply filters
        if ($request->has('form_phase_id') && $request->form_phase_id) {
            $query->whereHas('form.formAccessControls.formPhaseDetails', function ($q) use ($request) {
                $q->where('form_phase_id', $request->form_phase_id);
            });
        }

        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'approved') {
                $query->where('can_proceed', true);
            } elseif ($request->status === 'pending') {
                $query->where('can_proceed', false);
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('submittedBy', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Submissions/ShowPeriod', [
            'submissionPeriod' => $period->load('submissionDates.submissionDateLabel'),
            'formPhases' => $formPhases,
            'submissions' => $submissions,
            'filters' => $request->only(['form_phase_id', 'status', 'search'])
        ]);
    }

    public function userShowSubmission(FormSubmission $submission)
    {
        $user = Auth::user();

        // Ensure user can only view their own submissions
        if ($submission->submitted_by !== $user->id) {
            abort(403, 'Unauthorized access to submission');
        }

        $submission->load([
            'form.formFields' => function ($query) {
                $query->orderBy('order');
            },
            'form.formFields.fieldType',
            'form.formFields.formFieldOptions',
            'formFieldResponses'
        ]);

        // Map responses for easy access
        $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
            return [$response->form_field_id => $response->value];
        });

        return Inertia::render('User/Submissions/ShowSubmission', [
            'submission' => $submission,
            'responses' => $responses
        ]);
    }

    public function adminShowSubmission(FormSubmission $submission)
    {
        // Only show submitted forms to admin
        if (!$submission->is_submitted) {
            abort(404, 'Submission not found');
        }

        $submission->load([
            'form.formFields' => function ($query) {
                $query->orderBy('order');
            },
            'form.formFields.fieldType',
            'form.formFields.formFieldOptions' => function ($query) {
                $query->orderBy('order');
            },
            'formFieldResponses',
            'submittedBy.studyProgram.faculty'
        ]);

        // Map responses for easy access
        $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
            return [$response->form_field_id => $response->value];
        });

        return Inertia::render('Submissions/ShowSubmission', [
            'submission' => $submission,
            'responses' => $responses
        ]);
    }
}
