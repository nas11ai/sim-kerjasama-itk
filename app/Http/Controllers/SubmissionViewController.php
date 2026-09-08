<?php

namespace App\Http\Controllers;

use App\Actions\Review\BuildReviewerDashboard;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\OutputTypeDefinition;
use App\Models\ResearchMember;
use App\Models\ResearchOutput;
use App\Models\ReviewComment;
use App\Models\Reviewer;
use App\Models\ReviewFormFieldResponse;
use App\Models\ReviewSummary;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\SubmissionStatus;
use App\Support\PhaseAccessGate;
use App\Support\ScoreVisibility;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionViewController extends Controller
{
    public function userIndex(Request $request)
    {
        $user = Auth::user();
        $user->load('organization.parent');

        // Get submission periods with user's accessible form phases
        $submissionPeriods = SubmissionPeriod::with([
            'submissionDates.submissionDateLabel',
            'submissionPeriodPhases.formPhase' => function ($query) use ($user) {
                $query->whereHas('formPhaseDetails.formAccessControl', function (Builder $q) use ($user) {
                    /** @var Builder<FormAccessControl> $q */
                    $q->accessibleBy($user);
                });
            },
        ])
            ->whereHas('submissionPeriodPhases.formPhase.formPhaseDetails.formAccessControl', function (Builder $query) use ($user) {
                /** @var Builder<FormAccessControl> $query */
                $query->accessibleBy($user);
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

        return Inertia::render('User/Submissions/IndexPage', [
            'submissionPeriods' => $submissionPeriods,
            'userRole' => $user->getRoleNames()->first(),
            'studyProgram' => $user->relationLoaded('organization') && $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'faculty' => ['name' => $user->organization->parent->name ?? ''],
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
            $query->where('name', 'ilike', "%{$search}%");
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

        return Inertia::render('Submissions/IndexPage', [
            'submissionPeriods' => $submissionPeriods,
            'filters' => $request->only(['search']),
        ]);
    }

    public function userShowPeriod(SubmissionPeriod $period)
    {
        $user = Auth::user();

        // Get form phases for this period that user can access
        $formPhases = FormPhase::whereHas('submissionPeriodPhases', function ($query) use ($period) {
            $query->where('submission_period_id', $period->id);
        })
            ->whereHas('formPhaseDetails.formAccessControl', function (Builder $query) use ($user) {
                /** @var Builder<FormAccessControl> $query */
                $query->accessibleBy($user);
            })
            ->with([
                'formPhaseDetails' => function ($query) use ($user) {
                    $query->whereHas('formAccessControl', function (Builder $q) use ($user) {
                        /** @var Builder<FormAccessControl> $q */
                        $q->accessibleBy($user);
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
                            // I5 — form_submissions tidak punya kolom submitted_at;
                            // ini sudah selalu null, bukan perubahan perilaku.
                            // Dilacak sebagai utang di final-fix-report M0.
                            'submitted_at' => null,
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
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
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

    /**
     * BR-SM-14 — taksonomi ∩ izin skema.
     *
     * Skema yang tidak menyatakan `allowed_output_types` tidak membatasi apa
     * pun; itu berbeda dari daftar kosong, yang berarti tidak ada tipe yang
     * diizinkan sama sekali.
     *
     * @return Collection<int, array{code: string, name: string}>
     */
    private function allowedOutputTypesFor(FormSubmission $submission)
    {
        $taxonomy = OutputTypeDefinition::query()->where('is_active', true)->get();

        $rules = $submission->scheme?->rules;
        $allowed = is_array($rules) ? ($rules['allowed_output_types'] ?? null) : null;

        if (is_array($allowed)) {
            $taxonomy = $taxonomy->whereIn('code', $allowed);
        }

        return $taxonomy
            ->map(fn (OutputTypeDefinition $t) => ['code' => $t->code, 'name' => $t->name])
            ->values();
    }

    public function userShowSubmission(FormSubmission $submission)
    {
        $user = Auth::user();

        // BR-SM-07 — akses level submission adalah lapis terpisah dari
        // FormAccessControl. Cek buatan sendiri di sini dulu melewatkan anggota
        // penelitian dan pemegang submissions.view-all.
        abort_unless($submission->isAccessibleBy($user), 403, 'Akses tidak sah terhadap pengajuan.');

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
                    'reviewer:reviewer_type',
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
            'total_comments' => $reviewComments->count(),
        ];

        // Get user role
        $userRole = $this->getUserRoleForSubmission($submission, $user);
        $isAssignedReviewer = false;
        $submissionReviewer = null;

        // Check if user is an assigned reviewer
        if ($userRole === 'reviewer') {
            $reviewer = Reviewer::where('user_id', $user->id)->first();

            if ($reviewer !== null) {
                $submissionReviewer = SubmissionReviewer::where([
                    'form_submission_id' => $submission->id,
                    'reviewer_id' => $reviewer->id,
                ])->first();

                if ($submissionReviewer instanceof SubmissionReviewer) {
                    $isAssignedReviewer = true;
                }
            }
        }

        // UPDATED: Get evaluation requirements using FormPhaseDetail
        $formPhaseDetail = $submission->getFormPhaseDetail();
        $hasReviewEvaluationForms = $formPhaseDetail && $formPhaseDetail->hasReviewEvaluationForms();

        // Get reviewer form assignments
        $reviewerFormAssignments = [];

        if ($submissionReviewer instanceof SubmissionReviewer) {
            $reviewerFormAssignments = $this->getReviewerAssignments(
                $submissionReviewer,
                $formPhaseDetail
            );
        }

        // Calculate evaluation status
        $hasPendingEvaluations = false;
        $pendingEvaluationsCount = 0;
        $canCreateThread = false;

        if ($submissionReviewer instanceof SubmissionReviewer) {
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
            ->load(['reviewer.user'])
            ->map(function ($sr) {
                return [
                    'id' => $sr->id,
                    'user' => [
                        'id' => $sr->reviewer->user->id,
                        'name' => $sr->reviewer->user->name,
                        'email' => $sr->reviewer->user->email,
                    ],
                    'reviewer_type' => $sr->reviewer->reviewer_type,

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
            // Jawaban Policy atas empat tindakan utama, dikirim utuh supaya
            // layar tidak menyusun ulang aturannya sendiri. Tebakan lama
            // ("pemiliknya dan belum disubmit") melewatkan arsip periode
            // (BR-SM-10) dan status terminal (BR-SM-05).
            'abilities' => SubmissionAbilitiesData::forUser($user, $submission),
            // BR-SM-15 — Policy yang memutuskan siapa boleh mengubah target,
            // bukan sekadar "pemiliknya": status terminal ikut menutupnya.
            'canManageContent' => $user->can('manageTargetOutput', $submission),
            'targetOutputs' => $submission->targetOutputs()->get(['id', 'output_type']),
            // BR-SM-14 — dua penyaring bertingkat disatukan di sini supaya UI
            // tidak menawarkan tipe yang pasti ditolak Action.
            'allowedOutputTypes' => $this->allowedOutputTypesFor($submission),
            'researchMembers' => $submission->researchMembers()
                ->where('status', 'active')
                ->with('userProfile.user')
                ->get()
                ->map(fn (ResearchMember $m) => [
                    'id' => $m->id,
                    'name' => $m->userProfile->user->name,
                    'role' => $m->role,
                ]),
            'maxMembers' => $submission->scheme?->max_members,
            // Calon anggota: orang yang sudah punya profil dan belum terlibat
            // di pengajuan ini. Penyaring konflik kepentingan yang sebenarnya
            // tetap di ConflictOfInterestGuard — ini hanya supaya daftar yang
            // ditawarkan tidak penuh nama yang pasti ditolak.
            'memberCandidates' => User::query()
                ->whereHas('userProfile')
                ->where('id', '!=', $submission->submitted_by)
                ->whereDoesntHave('userProfile.researchMembers', fn ($q) => $q->where('form_submission_id', $submission->id))
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email']),
            'assignedReviewers' => $assignedReviewers,
            'reviewerFormAssignments' => $reviewerFormAssignments,
            'hasReviewEvaluationForms' => $hasReviewEvaluationForms,
            'evaluationRequirements' => $evaluationRequirements,
            'hasPendingEvaluations' => $hasPendingEvaluations,
            'pendingEvaluationsCount' => $pendingEvaluationsCount,
            'submissionStatus' => $submission->status,
            // BR-FE-08/15/16 — fase yang lewat batas tetap terbaca, hanya
            // terkunci; dan peneliti melihat *status* perpanjangan tanpa
            // alasannya. Keputusan siapa boleh membaca alasan ada di Policy,
            // bukan di sini.
            'phaseAccess' => PhaseAccessGate::presentFor(
                $submission,
                $formPhaseDetail,
                $user->can('viewOverrideReason', $submission),
            ),
            'canGrantOverride' => $user->can('grantOverride', $submission),
            // BR-REV-11/12 — penjagaannya di **payload**, bukan di CSS. Skor
            // yang tidak boleh dilihat tidak dikirim; kalau ia sampai ke
            // browser, tidak ada tampilan yang bisa menyembunyikannya dengan
            // aman. Alasannya ikut supaya layar bisa menjelaskan, bukan sekadar
            // menampilkan ruang kosong.
            'reviewerScores' => $this->reviewerScoresFor($user, $submission),
            // BR-FE-04 — laporan turunan hanya ditawarkan kalau induknya sudah
            // disetujui. Daftar fase yang belum punya laporan dihitung di sini
            // supaya layar tidak menawarkan yang pasti ditolak Action.
            'childPhases' => $this->availableChildPhasesFor($submission),
            // BR-RO-01..09 — luaran terealisasi. Daftar tipenya sudah disaring
            // taksonomi aktif dan jenis pengajuan, supaya layar tidak
            // menawarkan yang pasti ditolak guard.
            'researchOutputs' => $submission->researchOutputs()
                ->with('files:id,research_output_id,file_path')
                ->latest('id')
                ->get()
                ->map(fn (ResearchOutput $o): array => [
                    'id' => $o->id,
                    'output_type' => $o->output_type,
                    'metadata' => $o->metadata ?? [],
                    'file_count' => $o->files->count(),
                ])
                ->values(),
            'outputTypes' => $this->outputTypesFor($submission),
            'canManageOutputs' => $user->can('manageResearchOutput', $submission),
        ]);
    }

    /**
     * BR-RO-02, BR-RO-05, BR-RO-09 — tipe luaran yang sah untuk pengajuan ini.
     *
     * Disaring dua tingkat, sama seperti target luaran (BR-SM-14): taksonomi
     * aktif, lalu kesesuaian jenis pengajuan. Menawarkan tipe yang pasti
     * ditolak membuat peneliti mencoba lalu gagal tanpa tahu sebabnya.
     *
     * @return list<array{code: string, name: string, requires_files: bool, required_fields: list<string>}>
     */
    private function outputTypesFor(FormSubmission $submission): array
    {
        $submissionType = $submission->submissionType()?->name;

        return OutputTypeDefinition::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('submission_type')->orWhere('submission_type', $submissionType))
            ->orderBy('name')
            ->get()
            ->map(fn (OutputTypeDefinition $d): array => [
                'code' => $d->code,
                'name' => $d->name,
                'requires_files' => $d->requires_files,
                'required_fields' => $d->requiredMetadataKeys(),
            ])
            ->values()
            ->all();
    }

    /**
     * BR-REV-11 dan BR-REV-12 — hasil evaluasi yang boleh dibaca pemanggil.
     *
     * Dua sumbu yang berbeda dan sengaja tidak dicampur: reviewer internal
     * melihat skor rekannya setelah semua evaluasi selesai (BR-REV-11), dan
     * peneliti melihatnya setelah keputusan final (BR-REV-12). Yang tidak lolos
     * salah satunya menerima daftar kosong beserta alasannya — bukan daftar
     * penuh yang disembunyikan tampilan.
     *
     * @return array{visible: bool, reason: string|null, scores: list<array{reviewer: string, reviewer_type: string, submitted_at: string|null, answers: list<array{label: string, value: string|null}>}>}
     */
    private function reviewerScoresFor(User $user, FormSubmission $submission): array
    {
        $asPeer = ScoreVisibility::peerScoresVisibleTo($user, $submission);
        $asOwner = ScoreVisibility::visibleToResearcher($user, $submission);
        $asStaff = $user->can('submissions.view-all');

        if (!$asPeer && !$asOwner && !$asStaff) {
            return [
                'visible' => false,
                'reason' => $this->scoreHiddenReason($user, $submission),
                'scores' => [],
            ];
        }

        $scores = SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->where('status', 'active')
            ->with([
                'reviewer.user:id,name',
                'reviewerFormAssignments.reviewFormResponse.reviewFormFieldResponses.reviewFormField:id,label',
            ])
            ->get()
            ->flatMap(function (SubmissionReviewer $sr): array {
                $rows = [];

                foreach ($sr->reviewerFormAssignments as $assignment) {
                    $response = $assignment->reviewFormResponse;

                    // Evaluasi yang belum disubmit bukan penilaian setengah jadi
                    // yang boleh dibaca — ia belum jadi penilaian sama sekali.
                    if ($response === null || $response->status !== 'submitted') {
                        continue;
                    }

                    $reviewerUser = $sr->reviewer->user;

                    $rows[] = [
                        'reviewer' => $reviewerUser instanceof User ? $reviewerUser->name : 'Reviewer',
                        'reviewer_type' => $sr->reviewer->reviewer_type,
                        'submitted_at' => $response->submitted_at?->toDateTimeString(),
                        'answers' => $response->reviewFormFieldResponses
                            ->map(fn (ReviewFormFieldResponse $fr): array => [
                                'label' => $fr->reviewFormField->label,
                                'value' => $fr->value,
                            ])
                            ->values()
                            ->all(),
                    ];
                }

                return $rows;
            })
            ->values()
            ->all();

        return ['visible' => true, 'reason' => null, 'scores' => $scores];
    }

    private function scoreHiddenReason(User $user, FormSubmission $submission): string
    {
        if ($submission->submitted_by === $user->id) {
            // BR-REV-12 — bukan "review selesai", melainkan keputusan final.
            return 'Hasil review terbuka setelah keputusan akhir atas pengajuan ini diambil.';
        }

        return 'Skor reviewer lain terbuka bagi reviewer internal setelah seluruh evaluasi selesai.';
    }

    /**
     * Fase yang masih boleh melahirkan laporan turunan untuk pengajuan ini.
     *
     * Kosong selama induknya belum APPROVED (BR-FE-04) dan menyusut setiap
     * kali satu laporan dibuat — satu laporan per (induk, fase) (BR-MON-03).
     *
     * @return list<array{id: int, title: string}>
     */
    private function availableChildPhasesFor(FormSubmission $submission): array
    {
        if (!$submission->status instanceof Approved || $submission->parent_submission_id !== null) {
            return [];
        }

        $sudahAda = FormSubmission::query()
            ->where('parent_submission_id', $submission->id)
            ->pluck('form_phase_detail_id');

        return FormPhaseDetail::query()
            ->whereHas('formAccessControl', fn ($q) => $q->where('form_id', $submission->form_id))
            ->whereNotIn('id', $sudahAda)
            ->where('id', '!=', $submission->form_phase_detail_id)
            ->with('formPhase')
            ->ordered()
            ->get()
            ->map(function (FormPhaseDetail $detail): array {
                $phase = $detail->formPhase;

                return [
                    'id' => $detail->id,
                    'title' => $phase instanceof FormPhase ? $phase->title : 'Fase #'.$detail->id,
                ];
            })
            ->values()
            ->all();
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
                'submissionReviewers.reviewer:reviewer_type',
                'submissionReviewers.reviewerFormAssignments.reviewEvaluationForm:id,title',
                'submissionReviewers.reviewerFormAssignments.reviewFormResponse:id,status',
            ]);

            // Load review summaries
            $reviewSummaries = [];
            if (class_exists('App\Models\ReviewSummary')) {
                $reviewSummaries = ReviewSummary::where('form_submission_id', $submission->id)
                    ->with([
                        'reviewer.user:id,name,email',
                        'reviewer:reviewer_type',
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
                    'reviewer_type' => $submissionReviewer->reviewer->reviewer_type,
                ];
            })->toArray();

            // Get available reviewers for assignment
            $assignedReviewerIds = $submission->submissionReviewers->pluck('reviewer_id')->toArray();
            $availableReviewers = [];

            if (class_exists('App\Models\Reviewer')) {
                $today = Carbon::today();
                $availableReviewers = Reviewer::with(['user'])
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
                            'role' => $reviewer->reviewer_type,
                        ];
                    })
                    ->toArray();
            }

            // Check if current user is an assigned reviewer and get evaluation status
            $currentUser = auth()->user();
            $isAssignedReviewer = false;
            $hasPendingEvaluations = false;
            $pendingEvaluationsCount = 0;

            if (!$currentUser->can('users.manage')) {
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
            if ($currentUser->can('users.manage')) {
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

            if (!$currentUser->can('users.manage')) {
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
            if ($currentUser->can('users.manage')) {
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
                'canAssignReviewers' => $currentUser->can('users.manage'),
                // Policy yang memutuskan, bukan permission mentah — supaya
                // tombol tidak muncul untuk aksi yang pasti ditolak (ADR 0015).
                'canReject' => $currentUser->can('reject', $submission),
                'canWithdraw' => $currentUser->can('withdraw', $submission),
                'canTransferOwnership' => $currentUser->can('submissions.view-all'),
                // BR-SM-09 — hanya co_investigator aktif yang boleh menerima
                // kepemilikan; menyaringnya di sini supaya UI tidak menawarkan
                // orang yang pasti ditolak Action.
                'ownershipCandidates' => $submission->researchMembers()
                    ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
                    ->where('status', 'active')
                    ->with('userProfile.user')
                    ->get()
                    ->map(fn (ResearchMember $m) => [
                        'id' => $m->userProfile->user?->id,
                        'name' => $m->userProfile->user?->name,
                        'email' => $m->userProfile->user?->email,
                    ])
                    ->filter(fn (array $c) => $c['id'] !== null)
                    ->values(),
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
                    'total_comments' => 0,
                ],
                'availableReviewers' => [],
                'canAssignReviewers' => auth()->user()->can('users.manage'),
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
        if ($user->can('users.manage')) {
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
        if ($user->can('users.manage')) {
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

    /** Dasbor reviewer: penugasan aktif dan sisa pekerjaannya. */
    public function reviewerDashboard(): Response
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        $reviewer = Reviewer::where('user_id', $user->id)->first();

        abort_if($reviewer === null, 403, 'Anda tidak terdaftar sebagai reviewer.');

        $dashboard = BuildReviewerDashboard::run($reviewer);

        return Inertia::render('Reviewer/DashboardPage', [
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type,
                'user' => [
                    'name' => $reviewer->user?->name,
                    'email' => $reviewer->user?->email,
                ],
            ],
            'stats' => $dashboard['stats'],
            'assignments' => $dashboard['assignments'],
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
                    $query->where('name', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('form', function ($query) use ($search) {
                        $query->where('title', 'ilike', "%{$search}%");
                    });
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Reviewer/Submissions/IndexPage', [
            'submissions' => $submissions,
            'filters' => $request->only(['status', 'search']),
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type,

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
            'submissionReviewers.reviewer:reviewer_type',
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
                        'reviewer:reviewer_type',
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
                'reviewer_type' => $submissionReviewer->reviewer->reviewer_type,

            ];
        })->toArray();

        $submissionData = [
            'id' => $submission->id,
            'is_submitted' => $submission->is_submitted,
            'status' => $submission->status,
            'created_at' => $submission->created_at ? $submission->created_at->toISOString() : null,
            'updated_at' => $submission->updated_at ? $submission->updated_at->toISOString() : null,
            // I5 — form_submissions tidak punya kolom submitted_at; ini
            // sudah selalu null, bukan perubahan perilaku. Dilacak sebagai
            // utang di final-fix-report M0.
            'submitted_at' => null,
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

        return Inertia::render('Reviewer/Submissions/ShowPage', [
            'submission' => $submissionData,
            'responses' => $responses,
            'myReviewSummary' => $myReviewSummary,
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type,
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
