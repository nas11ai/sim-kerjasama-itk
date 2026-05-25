<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\SubmissionStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            },
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
                'faculty' => ['name' => $studyProgram->faculty->name],
            ] : null,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = SubmissionPeriod::with([
            'submissionDates.submissionDateLabel',
            'submissionPeriodPhases.formPhase',
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
                        count(case when status = "approved" then 1 end) as approved_submissions,
                        count(case when status != "approved" then 1 end) as pending_review
                    ')
                    ->first();

                $period->total_submissions = $submissionStats->total_submissions ?? 0;
                $period->approved_submissions = $submissionStats->approved_submissions ?? 0;
                $period->pending_review = $submissionStats->pending_review ?? 0;

                return $period;
            });

        return Inertia::render('Submissions/Index', [
            'submissionPeriods' => $submissionPeriods,
            'filters' => $request->only(['search']),
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
                },
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
                            'can_proceed' => $submission->canProceed(),
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
            'formPhases' => $formPhases,
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
            'submittedBy:id,name,email',
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
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('submittedBy', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(15);

        $submissionStatuses = SubmissionStatus::options();

        return Inertia::render('Submissions/ShowPeriod', [
            'submissionPeriod' => $period->load('submissionDates.submissionDateLabel'),
            'formPhases' => $formPhases,
            'submissions' => $submissions,
            'submissionStatuses' => $submissionStatuses,
            'filters' => $request->only(['form_phase_id', 'status', 'search']),
        ]);
    }

    public function userShowSubmission(FormSubmission $submission)
    {
        $user = Auth::user();

        // Check permission
        $canView = $submission->submitted_by === $user->id ||
            $this->canUserReview($submission, $user);

        if (!$canView) {
            abort(403, 'Akses tidak sah terhadap pengajuan.');
        }

        // Load submission with relationships
        $submission->load([
            'form.formFields' => function ($query) {
                $query->orderBy('order');
            },
            'form.formFields.fieldType',
            'form.formFields.formFieldOptions',
            'formFieldResponses',
            'submittedBy:id,name,email',
            'reviewSummaries' => function ($query) {
                $query->with([
                    'reviewer.user:id,name',
                    'reviewer.reviewerRole:id,name',
                    'attachments',
                ]);
            },
        ]);

        // Map responses
        $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
            return [$response->form_field_id => $response->value];
        });

        // Load review comments
        $reviewComments = ReviewComment::whereIn(
            'review_summary_id',
            $submission->reviewSummaries->pluck('id')
        )->with([
            'user:id,name',
            'reviewer.user:id,name',
            'attachments',
            'replies' => function ($q) {
                $q->with(['user:id,name', 'reviewer.user:id,name', 'attachments'])
                    ->orderBy('created_at', 'asc');
            },
        ])->whereNull('parent_comment_id')
            ->orderBy('created_at', 'desc')
            ->get();

        // Review statistics
        $reviewStats = [
            'total_reviewers' => $submission->submissionReviewers->count(),
            'open_reviews' => $submission->reviewSummaries->where('status', 'open')->count(),
            'resolved_reviews' => $submission->reviewSummaries->where('status', 'resolved')->count(),
            'closed_reviews' => $submission->reviewSummaries->where('status', 'closed')->count(),
            'total_comments' => $reviewComments->count(),
        ];

        // Get user role
        $userRole = $this->getUserRoleForSubmission($submission, $user);
        $isAssignedReviewer = false;
        $submissionReviewer = null;

        // Check if user is an assigned reviewer
        if ($userRole === 'reviewer') {
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer) {
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id,
                ])->first();

                $isAssignedReviewer = (bool) $submissionReviewer;
            }
        }

        // UPDATED: Get evaluation requirements using FormPhaseDetail
        $formPhaseDetail = $submission->getFormPhaseDetail();
        $hasReviewEvaluationForms = $formPhaseDetail && $formPhaseDetail->hasReviewEvaluationForms();

        // Get reviewer form assignments
        $reviewerFormAssignments = [];

        if ($isAssignedReviewer && $submissionReviewer) {
            $reviewerFormAssignments = $this->getReviewerAssignments(
                $submissionReviewer,
                $formPhaseDetail
            );
        }

        // Calculate evaluation status
        $hasPendingEvaluations = false;
        $pendingEvaluationsCount = 0;
        $canCreateThread = false;

        if ($isAssignedReviewer && $submissionReviewer) {
            if ($hasReviewEvaluationForms) {
                // Count REQUIRED forms only
                $requiredForms = collect($reviewerFormAssignments)
                    ->filter(fn ($a) => $a['is_required']);

                // Count completed REQUIRED forms
                $completedRequired = $requiredForms
                    ->filter(fn ($a) => ($a['review_form_response']['status'] ?? null) === 'submitted');

                $pendingEvaluationsCount = $requiredForms->count() - $completedRequired->count();
                $hasPendingEvaluations = $pendingEvaluationsCount > 0;

                // Can create thread if all REQUIRED forms are completed
                $canCreateThread = !$hasPendingEvaluations && $requiredForms->count() > 0;

                // If no required forms, can create immediately
                if ($requiredForms->count() === 0) {
                    $canCreateThread = true;
                    $hasPendingEvaluations = false;
                }
            } else {
                // No evaluation forms, can create immediately
                $canCreateThread = true;
            }
        }

        // Format assigned reviewers
        $assignedReviewers = $submission->submissionReviewers
            ->load(['reviewer.user', 'reviewer.reviewerRole'])
            ->map(function ($sr) {
                return [
                    'id' => $sr->id,
                    'user' => [
                        'id' => $sr->reviewer->user->id,
                        'name' => $sr->reviewer->user->name,
                        'email' => $sr->reviewer->user->email,
                    ],
                    'reviewer_role' => [
                        'id' => $sr->reviewer->reviewerRole->id,
                        'name' => $sr->reviewer->reviewerRole->name,
                    ],
                ];
            })->toArray();

        // Evaluation requirements
        $evaluationRequirements = [
            'required' => $hasReviewEvaluationForms,
            'has_forms' => $hasReviewEvaluationForms,
            'message' => $this->getEvaluationMessage(
                $hasReviewEvaluationForms,
                $isAssignedReviewer,
                count($reviewerFormAssignments),
                $hasPendingEvaluations
            ),
        ];

        return Inertia::render('User/Submissions/ShowSubmission', [
            'submission' => $submission->toArray(),
            'responses' => $responses,
            'reviewComments' => $reviewComments,
            'reviewStats' => $reviewStats,
            'canCreateThread' => $canCreateThread,
            'canReview' => $isAssignedReviewer,
            'userRole' => $userRole,
            'isOwnSubmission' => $submission->submitted_by === $user->id,
            'assignedReviewers' => $assignedReviewers,
            'reviewerFormAssignments' => $reviewerFormAssignments,
            'hasReviewEvaluationForms' => $hasReviewEvaluationForms,
            'evaluationRequirements' => $evaluationRequirements,
            'hasPendingEvaluations' => $hasPendingEvaluations,
            'pendingEvaluationsCount' => $pendingEvaluationsCount,
            'submissionStatus' => $submission->status,
        ]);
    }

    public function adminShowSubmission(FormSubmission $submission)
    {
        // Only show submitted forms to admin
        if (!$submission->is_submitted) {
            abort(404, 'Pengajuan tidak ditemukan.');
        }

        try {
            $submission->load([
                'form.formFields' => function ($query) {
                    $query->orderBy('order');
                },
                'form.formFields.fieldType',
                'form.formFields.formFieldOptions',
                'formFieldResponses',
                'submittedBy:id,name,email',
                // Load assigned reviewers melalui SubmissionReviewer
                'submissionReviewers.reviewer.user:id,name,email',
                'submissionReviewers.reviewer.reviewerRole:id,name',
                'submissionReviewers.reviewerFormAssignments.reviewEvaluationForm:id,title',
                'submissionReviewers.reviewerFormAssignments.reviewFormResponse:id,status',
            ]);

            // Load review summaries
            $reviewSummaries = [];
            if (class_exists('App\Models\ReviewSummary')) {
                $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)
                    ->with([
                        'reviewer.user:id,name,email',
                        'reviewer.reviewerRole:id,name',
                        'attachments',
                    ])
                    ->get()
                    ->toArray();
            }

            // Load review comments
            $reviewComments = [];
            if (class_exists('App\Models\ReviewComment') && !empty($reviewSummaries)) {
                $reviewSummaryIds = collect($reviewSummaries)->pluck('id')->toArray();

                if (!empty($reviewSummaryIds)) {
                    $reviewComments = ReviewComment::whereIn('review_summary_id', $reviewSummaryIds)
                        ->with([
                            'user:id,name',
                            'reviewer.user:id,name',
                            'attachments',
                            'replies' => function ($q) {
                                $q->with(['user:id,name', 'reviewer.user:id,name', 'attachments'])
                                    ->orderBy('created_at', 'asc');
                            },
                        ])
                        ->whereNull('parent_comment_id')
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->toArray();
                }
            }

            // Map responses
            $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
                return [$response->form_field_id => $response->value];
            });

            // Get review statistics
            $reviewStats = [
                'total_reviewers' => $submission->submissionReviewers->count(),
                'open_reviews' => collect($reviewSummaries)->where('status', 'open')->count(),
                'resolved_reviews' => collect($reviewSummaries)->where('status', 'resolved')->count(),
                'closed_reviews' => collect($reviewSummaries)->where('status', 'closed')->count(),
                'total_comments' => count($reviewComments),
            ];

            // Get assigned reviewers
            $assignedReviewers = $submission->submissionReviewers->map(function ($submissionReviewer) {
                return [
                    'id' => $submissionReviewer->id, // submission_reviewer id
                    'user' => [
                        'id' => $submissionReviewer->reviewer->user->id,
                        'name' => $submissionReviewer->reviewer->user->name,
                        'email' => $submissionReviewer->reviewer->user->email,
                    ],
                    'reviewer_role' => [
                        'id' => $submissionReviewer->reviewer->reviewerRole->id,
                        'name' => $submissionReviewer->reviewer->reviewerRole->name,
                    ],
                ];
            })->toArray();

            // Get available reviewers for assignment
            $assignedReviewerIds = $submission->submissionReviewers->pluck('reviewer_id')->toArray();
            $availableReviewers = [];

            if (class_exists('App\Models\Reviewer')) {
                $today = Carbon::today();
                $availableReviewers = Reviewer::with(['user', 'reviewerRole'])
                    ->where(function ($q) use ($today) {
                        $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                    })
                    ->whereNotIn('id', $assignedReviewerIds)
                    ->whereHas('user', function ($query) use ($submission) {
                        $query->where('id', '!=', $submission->submitted_by);
                    })
                    ->get()
                    ->map(function ($reviewer) {
                        return [
                            'id' => $reviewer->id,
                            'name' => $reviewer->user->name,
                            'email' => $reviewer->user->email,
                            'role' => $reviewer->reviewerRole->name,
                        ];
                    })
                    ->toArray();
            }

            // Check if current user is an assigned reviewer and get evaluation status
            $currentUser = auth()->user();
            $isAssignedReviewer = false;
            $hasPendingEvaluations = false;
            $pendingEvaluationsCount = 0;

            if (!$currentUser->hasRole(['Super Admin', 'Admin'])) {
                $reviewer = Reviewer::where('user_id', $currentUser->id)->first();

                if ($reviewer) {
                    $submissionReviewer = SubmissionReviewer::where([
                        'form_submission_id' => $submission->id,
                        'reviewer_id' => $reviewer->id,
                    ])->first();

                    if ($submissionReviewer) {
                        $isAssignedReviewer = true;

                        // Check evaluation status
                        $pendingEvaluationsCount = $submissionReviewer->reviewerFormAssignments()
                            ->where('is_active', true)
                            ->whereDoesntHave('reviewFormResponse', function ($q) {
                                $q->where('status', 'submitted');
                            })
                            ->count();

                        $hasPendingEvaluations = $pendingEvaluationsCount > 0;
                    }
                }
            }

            // Determine canReview and canCreateThread
            $canReview = $this->canUserReview($submission, $currentUser);
            $canCreateThread = $isAssignedReviewer && !$hasPendingEvaluations;

            // For admins, they can always create threads
            if ($currentUser->hasRole(['Super Admin', 'Admin'])) {
                $canCreateThread = true;
            }

            // Convert submission to array
            $submissionData = [
                'id' => $submission->id,
                'is_submitted' => $submission->is_submitted,
                'status' => $submission->status,
                'created_at' => $submission->created_at,
                'updated_at' => $submission->updated_at,
                'submitted_by' => $submission->submittedBy->toArray(),
                'form' => $submission->form->toArray(),
                'review_summaries' => $reviewSummaries,
                'review_comments' => $reviewComments,
                'assigned_reviewers' => $assignedReviewers,
            ];

            // Get evaluation requirements
            $evaluationRequirements = $submission->getEvaluationRequirements();
            $hasReviewEvaluationForms = $submission->hasReviewEvaluationForms();

            // Check if current user is an assigned reviewer and get evaluation status
            $currentUser = auth()->user();
            $isAssignedReviewer = false;
            $hasPendingEvaluations = false;
            $pendingEvaluationsCount = 0;

            if (!$currentUser->hasRole(['Super Admin', 'Admin'])) {
                $reviewer = Reviewer::where('user_id', $currentUser->id)->first();

                if ($reviewer) {
                    $submissionReviewer = SubmissionReviewer::where([
                        'form_submission_id' => $submission->id,
                        'reviewer_id' => $reviewer->id,
                    ])->first();

                    if ($submissionReviewer) {
                        $isAssignedReviewer = true;

                        // Only check pending evaluations if forms exist
                        if ($hasReviewEvaluationForms) {
                            $pendingEvaluationsCount = $submissionReviewer->reviewerFormAssignments()
                                ->where('is_active', true)
                                ->whereDoesntHave('reviewFormResponse', function ($q) {
                                    $q->where('status', 'submitted');
                                })
                                ->count();

                            $hasPendingEvaluations = $pendingEvaluationsCount > 0;
                        }
                    }
                }
            }

            // Determine canReview and canCreateThread
            $canReview = $this->canUserReview($submission, $currentUser);

            // Logic for canCreateThread:
            // - Admin can always create
            // - If no evaluation forms exist, reviewer can create immediately
            // - If evaluation forms exist, must complete them first
            if ($currentUser->hasRole(['Super Admin', 'Admin'])) {
                $canCreateThread = true;
            } elseif ($isAssignedReviewer) {
                if ($hasReviewEvaluationForms) {
                    // Has evaluation forms - must complete first
                    $canCreateThread = !$hasPendingEvaluations;
                } else {
                    // No evaluation forms - can create immediately
                    $canCreateThread = true;
                }
            } else {
                $canCreateThread = false;
            }

            return Inertia::render('Submissions/ShowSubmission', [
                'submission' => $submissionData,
                'responses' => $responses,
                'reviewStats' => $reviewStats,
                'availableReviewers' => $availableReviewers,
                'canAssignReviewers' => $currentUser->hasRole(['Super Admin', 'Admin']),
                'canReview' => $canReview,
                'canCreateThread' => $canCreateThread,
                'hasPendingEvaluations' => $hasPendingEvaluations,
                'pendingEvaluationsCount' => $pendingEvaluationsCount,
                'hasReviewEvaluationForms' => $hasReviewEvaluationForms,
                'evaluationRequirements' => $evaluationRequirements,
                'userRole' => $this->getUserRoleForSubmission($submission, $currentUser),
                'assignedReviewers' => $assignedReviewers, // Already in correct format
                'reviewerFormAssignments' => [], // Add if needed for admin
                'submissionStatus' => $submission->status, // ADDED THIS
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in adminShowSubmission: '.$e->getMessage(), [
                'submission_id' => $submission->id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback
            $submission->load([
                'form.formFields' => function ($query) {
                    $query->orderBy('order');
                },
                'form.formFields.fieldType',
                'form.formFields.formFieldOptions',
                'formFieldResponses',
                'submittedBy:id,name,email',
            ]);

            $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
                return [$response->form_field_id => $response->value];
            });

            return Inertia::render('Submissions/ShowSubmission', [
                'submission' => $submission->toArray(),
                'responses' => $responses,
                'reviewStats' => [
                    'total_reviewers' => 0,
                    'open_reviews' => 0,
                    'resolved_reviews' => 0,
                    'closed_reviews' => 0,
                    'total_comments' => 0,
                ],
                'availableReviewers' => [],
                'canAssignReviewers' => auth()->user()->hasRole(['Super Admin', 'Admin']),
                'canReview' => false,
                'canCreateThread' => false,
                'hasPendingEvaluations' => false,
                'pendingEvaluationsCount' => 0,
                'userRole' => $this->getUserRoleForSubmission($submission, auth()->user()),
                'error' => 'Sistem review sedang tidak tersedia untuk sementara.',
            ]);
        }
    }

    // Helper method to check if user can review
    private function canUserReview(FormSubmission $submission, $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        $reviewer = Reviewer::where('user_id', $user->id)->latest()->first();
        if ($reviewer) {
            return SubmissionReviewer::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ])->exists();
        }

        return false;
    }

    // Helper method to determine user's role for this submission
    private function getUserRoleForSubmission(FormSubmission $submission, $user): string
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return 'admin';
        }

        if ($submission->submitted_by === $user->id) {
            return 'submitter';
        }

        if ($this->canUserReview($submission, $user)) {
            return 'reviewer';
        }

        return 'user';
    }

    // Method untuk reviewer dashboard (optional - jika ingin ada dashboard khusus reviewer)
    public function reviewerDashboard()
    {
        $user = Auth::user();

        // user = reviewer
        $reviewer = Reviewer::with('reviewerRole')->where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer.');
        }

        $forms = Form::whereHas('formAccessControls')->with(['formAccessControls.formPhaseDetails'])->get(['id', 'title']);

        $assignedSubmissions = FormSubmission::whereHas('reviewSummaries', function ($q) use ($reviewer) {
            $q->where('reviewer_id', $reviewer->id);
        })
            ->with([
                'form:id,title',
                'submittedBy:id,name,email',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Statistik reviewer
        $stats = [
            'total_assigned' => ReviewSummary::where('reviewer_id', $reviewer->id)->count(),
            'pending_reviews' => ReviewSummary::where('reviewer_id', $reviewer->id)->where('status', 'open')->count(),
            'completed_reviews' => ReviewSummary::where('reviewer_id', $reviewer->id)->where('status', 'resolved')->count(),
            'rejected_reviews' => ReviewSummary::where('reviewer_id', $reviewer->id)->where('status', 'closed')->count(),
        ];

        return Inertia::render('Reviewer/Dashboard', [
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_role' => $reviewer->reviewer_role,
                'user' => [
                    'name' => $reviewer->user->name,
                    'email' => $reviewer->user->email,
                ],
            ],
            'stats' => $stats,
            'assignedSubmissions' => $assignedSubmissions,
            'forms' => $forms,
        ]);
    }

    // Method untuk melihat semua submissions yang bisa direview oleh user (sebagai reviewer)
    public function reviewerSubmissions(Request $request)
    {
        $user = Auth::user();
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer.');
        }

        $query = FormSubmission::whereHas('submissionReviewers', function ($q) use ($reviewer) {
            $q->where('reviewer_id', $reviewer->id);
        })
            ->with([
                'form:id,title',
                'submittedBy:id,name,email',
                'reviewSummaries',
                'submissionReviewers',
            ]);

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->whereHas('submissionReviewers', function ($q) use ($reviewer, $request) {
                $q->where('reviewer_id', $reviewer->id)
                    ->where('evaluation_status', $request->status);
            });
        }

        // Search by submitter name or form title
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('submittedBy', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('form', function ($query) use ($search) {
                        $query->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Reviewer/Submissions/Index', [
            'submissions' => $submissions,
            'filters' => $request->only(['status', 'search']),
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_role' => [
                    'id' => $reviewer->reviewerRole->id,
                    'name' => $reviewer->reviewerRole->name,
                ],
            ],
        ]);
    }

    public function reviewerShowSubmission(FormSubmission $submission)
    {
        $user = Auth::user();
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'Anda tidak terdaftar sebagai reviewer.');
        }

        $isAssigned = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ])->exists();

        if (!$isAssigned) {
            abort(403, 'Anda tidak ditugaskan untuk review pengajuan ini.');
        }

        $submission->load([
            'form.formFields' => function ($query) {
                $query->orderBy('order');
            },
            'form.formFields.fieldType',
            'form.formFields.formFieldOptions',
            'formFieldResponses',
            'submittedBy:id,name,email',
            'submissionReviewers.reviewer.user:id,name,email',
            'submissionReviewers.reviewer.reviewerRole:id,name',
        ]);

        $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
            return [$response->form_field_id => $response->value];
        });

        $reviewSummaries = [];
        try {
            if (class_exists('App\Models\ReviewSummary')) {
                $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)
                    ->with([
                        'reviewer.user:id,name,email',
                        'reviewer.reviewerRole:id,name',
                    ])
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            \Log::warning('Tidak dapat memuat ringkasan review: '.$e->getMessage());
        }

        $myReviewSummary = collect($reviewSummaries)->firstWhere('reviewer_id', $reviewer->id);

        $assignedReviewers = $submission->submissionReviewers->map(function ($submissionReviewer) {
            return [
                'id' => $submissionReviewer->reviewer->id,
                'user' => [
                    'id' => $submissionReviewer->reviewer->user->id,
                    'name' => $submissionReviewer->reviewer->user->name,
                    'email' => $submissionReviewer->reviewer->user->email,
                ],
                'reviewer_role' => [
                    'id' => $submissionReviewer->reviewer->reviewerRole->id,
                    'name' => $submissionReviewer->reviewer->reviewerRole->name,
                ],
            ];
        })->toArray();

        $submissionData = [
            'id' => $submission->id,
            'is_submitted' => $submission->is_submitted,
            'status' => $submission->status,
            'created_at' => $submission->created_at ? $submission->created_at->toISOString() : null,
            'updated_at' => $submission->updated_at ? $submission->updated_at->toISOString() : null,
            'submitted_at' => $submission->submitted_at ? $submission->submitted_at->toISOString() : null,
            'submitted_by' => [
                'id' => $submission->submittedBy->id,
                'name' => $submission->submittedBy->name,
                'email' => $submission->submittedBy->email,
            ],
            'form' => [
                'id' => $submission->form->id,
                'title' => $submission->form->title,
                'description' => $submission->form->description,
                'form_fields' => $submission->form->formFields->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'label' => $field->label,
                        'is_required' => $field->is_required,
                        'order' => $field->order,
                        'field_type' => [
                            'name' => $field->fieldType->name,
                        ],
                        'form_field_options' => $field->formFieldOptions->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'label' => $option->label,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ],
            'assigned_reviewers' => $assignedReviewers,
        ];

        return Inertia::render('Reviewer/Submissions/Show', [
            'submission' => $submissionData,
            'responses' => $responses,
            'myReviewSummary' => $myReviewSummary,
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_role' => [
                    'id' => $reviewer->reviewerRole->id,
                    'name' => $reviewer->reviewerRole->name,
                ],
            ],
            'canReview' => true,
            'userRole' => 'reviewer',

            'availableStatuses' => [
                ['value' => 'resolved', 'label' => 'Resolved'],
                ['value' => 'needs_revision', 'label' => 'Needs Revision'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
        ]);
    }

    public function updateStatus(Request $request, FormSubmission $submission)
    {
        $user = Auth::user();
        $reviewer = Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'You are not registered as a reviewer');
        }

        $isAssigned = SubmissionReviewer::where([
            'form_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ])->exists();

        if (!$isAssigned) {
            abort(403, 'Anda tidak ditugaskan untuk review pengajuan ini.');
        }

        $validated = $request->validate([
            'status' => 'required|in:resolved,needs_revision,rejected',
            'notes' => 'nullable|string',
        ]);

        try {
            $reviewSummary = ReviewSummary::updateOrCreate(
                [
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id,
                ],
                [
                    'status' => $validated['status'],
                    'summary_notes' => $validated['notes'],
                ]
            );

            if ($validated['status'] === 'resolved') {
                $totalReviewers = SubmissionReviewer::where('form_submission_id', $submission->id)->count();
                $resolvedReviews = ReviewSummary::where('form_submission_id', $submission->id)
                    ->where('status', 'resolved')
                    ->count();

                if ($totalReviewers === $resolvedReviews) {
                    $submission->update(['status' => 'approved']);
                }
            }

            return back()->with('success', 'Review berhasil dikirim');
        } catch (\Exception $e) {
            \Log::error('Error updating review: '.$e->getMessage());

            return back()->withErrors(['error' => 'Failed to submit review']);
        }
    }

    protected function getReviewerAssignments($submissionReviewer, $formPhaseDetail)
    {
        if (!$formPhaseDetail) {
            return [];
        }

        $assignments = [];

        // Get ALL available evaluation forms for this form phase detail
        $availableForms = $formPhaseDetail->activeReviewEvaluationForms()
            ->get(['id', 'title', 'description', 'is_required', 'order']);

        if ($availableForms->isEmpty()) {
            return []; // Return empty if no evaluation forms
        }

        // Get existing assignments for this reviewer
        $existingAssignments = $submissionReviewer->reviewerFormAssignments()
            ->with([
                'reviewEvaluationForm:id,title,description,is_required,form_phase_detail_id',
                'reviewFormResponse:id,reviewer_form_assignment_id,status,submitted_at',
            ])
            ->whereHas('reviewEvaluationForm', function ($q) use ($formPhaseDetail) {
                $q->where('form_phase_detail_id', $formPhaseDetail->id);
            })
            ->get()
            ->keyBy('review_evaluation_form_id');

        // Merge available forms with assignments
        foreach ($availableForms as $form) {
            $assignment = $existingAssignments->get($form->id);

            $assignments[] = [
                'id' => $assignment->id ?? null, // null if not started yet
                'review_evaluation_form' => [
                    'id' => $form->id,
                    'title' => $form->title,
                    'description' => $form->description,
                ],
                'form_phase_detail' => [
                    'id' => $formPhaseDetail->id,
                    'form_title' => $formPhaseDetail->formAccessControl->form->title ?? null,
                    'order' => $formPhaseDetail->order,
                ],
                'is_required' => $form->is_required,
                'due_date' => $assignment->due_date ?? null,
                'review_form_response' => $assignment && $assignment->reviewFormResponse ? [
                    'id' => $assignment->reviewFormResponse->id,
                    'status' => $assignment->reviewFormResponse->status,
                    'submitted_at' => $assignment->reviewFormResponse->submitted_at,
                ] : null,
            ];
        }

        return $assignments;
    }

    protected function getEvaluationMessage(
        bool $hasEvaluationForms,
        bool $isAssignedReviewer,
        int $assignmentsCount,
        bool $hasPendingEvaluations
    ): string {
        if (!$hasEvaluationForms) {
            return 'Tidak ada formulir evaluasi yang diperlukan untuk pengajuan ini.';
        }

        if (!$isAssignedReviewer) {
            return 'Anda tidak ditugaskan sebagai reviewer untuk pengajuan ini.';
        }

        // Has evaluation forms - reviewer is assigned
        if ($hasPendingEvaluations) {
            return 'Selesaikan semua formulir evaluasi yang diperlukan sebelum membuat thread review.';
        }

        return 'Semua evaluasi yang diperlukan telah selesai. Anda sekarang dapat membuat thread review.';
    }
}
