<?php

namespace App\Http\Controllers;

use App\Actions\FormSubmission\ResolveWizardSubmission;
use App\Actions\FormSubmission\StartSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Actions\Scheme\SyncSchemeSelection;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormFieldResponse;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\ReviewSummary;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\FormAccessService;
use App\Support\OptimisticLock;
use App\Support\PhaseAccessGate;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserFormController extends Controller
{
    protected $emailService;

    public function __construct(
        EmailNotificationService $emailService,
        private FormAccessService $formAccessService,
    ) {
        $this->emailService = $emailService;
    }

    public function dashboard()
    {
        $user = Auth::user();
        $user->load('organization.parent');

        $userRoles = $user->getRoleNames();
        $primaryRole = $userRoles->first() ?? 'user';

        // Check if user is a reviewer
        $reviewer = Reviewer::where('user_id', $user->id)->first();
        $isReviewer = $reviewer !== null;

        $permissions = $this->formAccessService->permissionNamesFor($user);
        $organizationAncestors = $this->formAccessService->organizationAncestorsFor($user);

        // Get submission periods with accessible form phases
        $submissionPeriods = SubmissionPeriod::with([
            'submissionDates.submissionDateLabel',
            'submissionPeriodPhases.formPhase.formPhaseDetails' => function ($query) use ($user) {
                $query->whereHas('formAccessControl', function (Builder $q) use ($user) {
                    /** @var Builder<FormAccessControl> $q */
                    $q->accessibleBy($user);
                })
                    ->with(['formAccessControl.form.formFields', 'phaseType'])
                    ->orderBy('order');
            },
        ])
            ->get()
            ->map(function ($period) use ($user, $permissions, $organizationAncestors) {
                // BR-FE-01 — keaktifan periode dihitung satu tempat saja
                // (SubmissionPeriodGate), sama dengan yang menggerbangi
                // pembuatan pengajuan. Salinan yang dulu ada di sini melupakan
                // is_force_closed, jadi peneliti melihat periode yang ditutup
                // Operator sebagai masih terbuka lalu ditolak saat mencoba.
                $dates = $period->submissionDates->sortBy('datetime');

                $period->is_active = SubmissionPeriodGate::isOpen($period);
                $period->status = SubmissionPeriodGate::displayStatus($period);
                $period->start_date = $dates->first()?->datetime;
                $period->end_date = $dates->last()?->datetime;

                // Process form phases with user progress
                $period->form_phases = $period->submissionPeriodPhases->map(function ($periodPhase) use ($user, $permissions, $organizationAncestors) {
                    $formPhase = $periodPhase->formPhase;

                    // Filter by permission AND organization subtree to avoid counting forms multiple times
                    $accessibleForms = $formPhase->formPhaseDetails->filter(function ($detail) use ($permissions, $organizationAncestors) {
                        $formAccessControl = $detail->formAccessControl;

                        if (!$formAccessControl) {
                            return false;
                        }

                        return in_array($formAccessControl->permission, $permissions, true)
                            && in_array((int) $formAccessControl->organization_id, $organizationAncestors, true);
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
            'studyProgram' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'faculty' => [
                    'name' => $user->organization->parent->name ?? '',
                ],
            ] : null,
            'isReviewer' => $isReviewer,
            'reviewStats' => $reviewStats,
            'reviewer' => $reviewer ? [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type,
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
            'reviewer' => [
                'id' => $reviewer->id,
                'reviewer_type' => $reviewer->reviewer_type,
            ],
        ]);
    }

    public function showFormPhase(SubmissionPeriod $period, FormPhase $phase, Request $request)
    {
        $user = Auth::user();

        // Get form access controls for this phase that user can access
        $formAccessControls = $phase->formPhaseDetails()
            ->whereHas('formAccessControl', function (Builder $query) use ($user) {
                /** @var Builder<FormAccessControl> $query */
                $query->accessibleBy($user);
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

    /**
     * C1 — resolusi FormPhaseDetail dari form_phase_id + form_id yang sudah
     * divalidasi. form_id saja ambigu kalau dipakai lebih dari satu
     * FormPhaseDetail (ADR 0004); pasangan form_phase_id + form_id masih
     * ambigu kalau satu Form dipakai oleh lebih dari satu FormAccessControl
     * (satu per organisasi) — jadi ikut men-scope dengan ->accessibleBy($user).
     * Gagal keras kalau tidak ketemu, bukan menulis NULL — dengan scope ini,
     * "tidak ketemu" juga berarti user tidak berwenang atas phase detail
     * tersebut. Dipakai satu-satunya oleh startSubmission() — saveDraft() dan
     * submitForm() sudah mendelegasikan resolusi barisnya ke
     * ResolveWizardSubmission (#25, #28).
     */
    private function resolveFormPhaseDetail(int $formPhaseId, int $formId, User $user): FormPhaseDetail
    {
        $formPhaseDetail = FormPhaseDetail::where('form_phase_id', $formPhaseId)
            ->whereHas('formAccessControl', function (Builder $query) use ($formId, $user) {
                /** @var Builder<FormAccessControl> $query */
                $query->where('form_id', $formId)
                    ->accessibleBy($user);
            })
            ->first();

        if ($formPhaseDetail === null) {
            abort(422, 'Kombinasi form dan fase formulir tidak ditemukan.');
        }

        return $formPhaseDetail;
    }

    public function startSubmission(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'form_id' => 'required|exists:forms,id',
            'submission_period_id' => 'required|exists:submission_periods,id',
            'form_phase_id' => 'required|exists:form_phases,id',
        ]);

        $form = Form::findOrFail($validated['form_id']);

        if (!$this->formAccessService->canAccessForm($user, $form)) {
            abort(403, 'Anda tidak memiliki akses ke formulir ini.');
        }

        $formPhaseDetail = $this->resolveFormPhaseDetail(
            (int) $validated['form_phase_id'],
            (int) $validated['form_id'],
            $user
        );

        StartSubmission::run(
            (int) $validated['form_id'],
            (int) $validated['submission_period_id'],
            $formPhaseDetail,
            $user
        );

        return redirect()->back();
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
            // BR-FE-14 — penyimpanan membawa cap waktu yang dilihat penyunting.
            // `nullable` di sini, bukan `required`: wajibnya ditegakkan
            // OptimisticLock **setelah** baris diresolusi, supaya "belum ada
            // baris" tetap terbaca 409 dan tidak tertukar jadi 422
            // ([SM-STATE-04]).
            'last_updated_at' => 'nullable|date',
        ]);

        $form = Form::findOrFail($validated['form_id']);
        if (!$this->formAccessService->canAccessForm($user, $form)) {
            abort(403, 'Anda tidak memiliki akses ke formulir ini.');
        }

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
            // #28 — resolver menggantikan firstOrCreate(): baris satu-satunya sumber
            // kebenaran adalah langkah 1 wizard (StartSubmission), bukan save-draft.
            $submission = ResolveWizardSubmission::run(
                (int) $validated['form_id'],
                (int) $validated['submission_period_id'],
                $user
            );

            // Belum ada baris sama sekali — peneliti melewati langkah 1.
            abort_if($submission === null, 409, 'Draft tidak ditemukan; mulai pengajuan dari langkah 1.');

            // BR-SM-05 — isi pengajuan terkunci pada status terminal. Resolver
            // sengaja tidak menyaring status supaya pembedaan 409/403 ini bisa
            // dibuat; [SM-STATE-04] menuntut 403, bukan 409.
            abort_unless($user->can('update', $submission), 403, 'Pengajuan ini sudah terkunci.');

            // BR-FE-08 — lewat batas fase, akses ditutup keras kecuali ada
            // FormSubmissionOverride yang masih berlaku untuk pasangan
            // pengajuan+fase ini. Force close periode mengalahkan keduanya.
            PhaseAccessGate::assertOpenFor($submission, $submission->formPhaseDetail);

            // BR-FE-14/BR-SM-12 — dua orang yang menyunting pengajuan yang sama
            // tidak boleh saling menimpa diam-diam. Diperiksa di dalam
            // transaksi, setelah baris diresolusi, supaya yang dibandingkan
            // adalah nilai yang benar-benar akan ditimpa.
            OptimisticLock::assertNotStale($submission, $validated['last_updated_at'] ?? null);

            $this->persistResponses($submission, $validated['responses'], $fileUploads);
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
            // BR-FE-14 — submit juga menulis isi, jadi ia ikut membawa cap
            // waktu. `nullable` dengan alasan yang sama seperti di saveDraft.
            'last_updated_at' => 'nullable|date',
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

        $form = Form::findOrFail($validated['form_id']);

        if (!$this->formAccessService->canAccessForm($user, $form)) {
            abort(403, 'Anda tidak memiliki akses ke formulir ini.');
        }

        // #28 — resolver menggantikan firstOrCreate(): baris satu-satunya sumber
        // kebenaran adalah langkah 1 wizard (StartSubmission), bukan submit.
        $submission = ResolveWizardSubmission::run(
            (int) $validated['form_id'],
            (int) $validated['submission_period_id'],
            $user
        );

        // Belum ada baris sama sekali — peneliti melewati langkah 1.
        abort_if($submission === null, 409, 'Draft tidak ditemukan; mulai pengajuan dari langkah 1.');

        // BR-SM-05 — isi pengajuan terkunci pada status terminal. Resolver
        // sengaja tidak menyaring status supaya pembedaan 409/403 ini bisa
        // dibuat; [SM-STATE-04] menuntut 403, bukan 409.
        abort_unless($user->can('update', $submission), 403, 'Pengajuan ini sudah terkunci.');

        // BR-FE-08 — sama seperti jalur simpan draft: batas fase menutup keras
        // kecuali ada override yang masih berlaku.
        PhaseAccessGate::assertOpenFor($submission, $submission->formPhaseDetail);

        DB::transaction(function () use ($validated, $fileUploads, $submission): void {
            OptimisticLock::assertNotStale($submission, $validated['last_updated_at'] ?? null);

            $this->persistResponses($submission, $validated['responses'], $fileUploads);
        });

        // BR-SM-01, BR-SM-02, BR-SM-04, BR-SM-16 — seluruh keputusan submit ada di
        // Action. Cabang needs_review yang dulu di sini dicabut: ia memanggil
        // transitionTo(Approved) dari DRAFT, transisi yang tidak diizinkan state
        // machine, sehingga selalu melempar TransitionNotFound (ADR 0016).
        SubmitFormSubmission::run($submission, $user);

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

    /**
     * @param  array<int, array{form_field_id: int|string, value: mixed}>  $responses
     * @param  array<int|string, string>  $fileUploads
     */
    private function persistResponses(FormSubmission $submission, array $responses, array $fileUploads): void
    {
        $submission->formFieldResponses()->delete();

        foreach ($responses as $index => $responseData) {
            $value = $fileUploads[$index] ?? $responseData['value'];

            if (empty($value) && $value !== '0' && $value !== 0) {
                continue;
            }

            FormFieldResponse::create([
                'form_submission_id' => $submission->id,
                'form_field_id' => $responseData['form_field_id'],
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }

        // BR-SCH-04/BR-SCH-10 (ADR 0005) — pilihan scheme_selector dipindahkan
        // ke kolom scheme_id begitu jawabannya tersimpan. Batas anggaran,
        // anggota, dan tipe luaran semuanya menanyakan kolom itu; membiarkan
        // pilihannya hanya sebagai jawaban form memaksa setiap penanya tahu
        // field mana yang menyimpannya.
        SyncSchemeSelection::run($submission);
    }
}
