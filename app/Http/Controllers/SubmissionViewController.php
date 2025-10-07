<?php

namespace App\Http\Controllers;

use App\Models\ReviewComment;
use App\Models\SubmissionPeriod;
use App\Models\FormPhase;
use App\Models\FormSubmission;
use App\Models\FormFieldResponse;
use App\SubmissionStatus;
use Carbon\Carbon;
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
            'filters' => $request->only(['form_phase_id', 'status', 'search'])
        ]);
    }

    public function userShowSubmission(FormSubmission $submission)
    {
        $user = Auth::user();

        // User can view their own submissions OR submissions they are assigned to review
        $canView = $submission->submitted_by === $user->id || $this->canUserReview($submission, $user);

        if (!$canView) {
            abort(403, 'Unauthorized access to submission');
        }

        $submission->load([
            'form.formFields' => function ($query) {
                $query->orderBy('order');
            },
            'form.formFields.fieldType',
            'form.formFields.formFieldOptions',
            'formFieldResponses',
            'submittedBy:id,name,email', // Add this for reviewer view
            // Load review data
            'reviewSummaries' => function ($query) {
                $query->with([
                    'reviewer.user:id,name',
                    'reviewer.reviewerRole:id,name',
                    'attachments'
                ]);
            }
        ]);

        // Load review comments
        $reviewComments = ReviewComment::whereIn('review_summary_id', $submission->reviewSummaries->pluck('id'))
            ->with([
                'user:id,name',
                'reviewer.user:id,name',
                'attachments',
                'replies' => function ($q) {
                    $q->with(['user:id,name', 'reviewer.user:id,name', 'attachments'])
                        ->orderBy('created_at', 'asc');
                }
            ])
            ->whereNull('parent_comment_id')
            ->orderBy('created_at', 'desc')
            ->get();

        // Map responses for easy access
        $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
            return [$response->form_field_id => $response->value];
        });

        // Get review statistics
        $reviewStats = [
            'total_reviewers' => $submission->reviewSummaries->count(),
            'open_reviews' => $submission->reviewSummaries->where('status', 'open')->count(),
            'resolved_reviews' => $submission->reviewSummaries->where('status', 'resolved')->count(),
            'closed_reviews' => $submission->reviewSummaries->where('status', 'closed')->count(),
            'total_comments' => $reviewComments->count(),
        ];

        $userRole = $this->getUserRoleForSubmission($submission, $user);

        return Inertia::render('User/Submissions/ShowSubmission', [
            'submission' => $submission, // tanpa append()
            'responses' => $responses,
            'reviewComments' => $reviewComments, // kirim terpisah
            'reviewStats' => $reviewStats,
            'canCreateThread' => true,
            'canReview' => $this->canUserReview($submission, $user),
            'userRole' => $userRole,
            'isOwnSubmission' => $submission->submitted_by === $user->id,
        ]);
    }

    public function adminShowSubmission(FormSubmission $submission)
    {
        // Only show submitted forms to admin
        if (!$submission->is_submitted) {
            abort(404, 'Submission not found');
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
            ]);

            // Load review summaries separately - bisa ada multiple per reviewer atau general threads
            $reviewSummaries = [];
            if (class_exists('App\Models\ReviewSummary')) {
                $reviewSummaries = \App\Models\ReviewSummary::where('form_submission_id', $submission->id)
                    ->with([
                        'reviewer.user:id,name,email',
                        'reviewer.reviewerRole:id,name',
                        'attachments'
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
                            }
                        ])
                        ->whereNull('parent_comment_id')
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->toArray();
                }
            }

            // Map responses for easy access
            $responses = $submission->formFieldResponses->mapWithKeys(function ($response) {
                return [$response->form_field_id => $response->value];
            });

            // Get review statistics from ReviewSummary
            $reviewStats = [
                'total_reviewers' => $submission->submissionReviewers->count(), // dari SubmissionReviewer
                'open_reviews' => collect($reviewSummaries)->where('status', 'open')->count(),
                'resolved_reviews' => collect($reviewSummaries)->where('status', 'resolved')->count(),
                'closed_reviews' => collect($reviewSummaries)->where('status', 'closed')->count(),
                'total_comments' => count($reviewComments),
            ];

            // Get assigned reviewers dari SubmissionReviewer (untuk display)
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
                    ]
                ];
            })->toArray();

            // Get available reviewers for assignment (exclude already assigned + submission owner)
            $assignedReviewerIds = $submission->submissionReviewers->pluck('reviewer_id')->toArray();

            $availableReviewers = [];
            if (class_exists('App\Models\Reviewer')) {
                $today = Carbon::today();
                $availableReviewers = \App\Models\Reviewer::with(['user', 'reviewerRole'])
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

            // Convert submission to array safely
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
                'assigned_reviewers' => $assignedReviewers, // dari SubmissionReviewer
            ];

            return Inertia::render('Submissions/ShowSubmission', [
                'submission' => $submissionData,
                'responses' => $responses,
                'reviewStats' => $reviewStats,
                'availableReviewers' => $availableReviewers,
                'canAssignReviewers' => auth()->user()->hasRole(['Super Admin', 'Admin']),
                'canReview' => $this->canUserReview($submission, auth()->user()),
                'userRole' => $this->getUserRoleForSubmission($submission, auth()->user()),
            ]);

        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Error in adminShowSubmission: ' . $e->getMessage(), [
                'submission_id' => $submission->id,
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to basic view
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
                'userRole' => $this->getUserRoleForSubmission($submission, auth()->user()),
                'error' => 'Review system temporarily unavailable',
            ]);
        }
    }

    // Helper method to check if user can review
    private function canUserReview(FormSubmission $submission, $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        $reviewer = \App\Models\Reviewer::where('user_id', $user->id)->first();
        if ($reviewer) {
            return \App\Models\SubmissionReviewer::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id
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

        $reviewer = \App\Models\Reviewer::where('user_id', $user->id)->first();
        if ($reviewer) {
            $isAssignedReviewer = \App\Models\ReviewSummary::where([
                'form_submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id
            ])->exists();

            if ($isAssignedReviewer) {
                return 'reviewer';
            }
        }

        return 'user';
    }

    // Method untuk reviewer dashboard (optional - jika ingin ada dashboard khusus reviewer)
    public function reviewerDashboard()
    {
        $user = Auth::user();
        $reviewer = \App\Models\Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'You are not registered as a reviewer');
        }

        // Get submissions assigned to this reviewer
        $assignedSubmissions = FormSubmission::whereHas('reviewSummaries', function ($query) use ($reviewer) {
            $query->where('reviewer_id', $reviewer->id);
        })
            ->with([
                'form:id,title',
                'submittedBy:id,name,email',
                'reviewSummaries' => function ($query) use ($reviewer) {
                    $query->where('reviewer_id', $reviewer->id);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Statistics
        $stats = [
            'total_assigned' => \App\Models\ReviewSummary::where('reviewer_id', $reviewer->id)->count(),
            'pending_reviews' => \App\Models\ReviewSummary::where('reviewer_id', $reviewer->id)
                ->where('status', 'open')->count(),
            'completed_reviews' => \App\Models\ReviewSummary::where('reviewer_id', $reviewer->id)
                ->where('status', 'resolved')->count(),
            'rejected_reviews' => \App\Models\ReviewSummary::where('reviewer_id', $reviewer->id)
                ->where('status', 'closed')->count(),
        ];

        return Inertia::render('Reviewer/Dashboard', [
            'assignedSubmissions' => $assignedSubmissions,
            'stats' => $stats,
            'reviewer' => $reviewer->load('reviewerRole')
        ]);
    }

    // Method untuk melihat semua submissions yang bisa direview oleh user (sebagai reviewer)
    public function reviewerSubmissions(Request $request)
    {
        $user = Auth::user();
        $reviewer = \App\Models\Reviewer::where('user_id', $user->id)->first();

        if (!$reviewer) {
            abort(403, 'You are not registered as a reviewer');
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
            'reviewer' => $reviewer->load('reviewerRole')
        ]);
    }
}
