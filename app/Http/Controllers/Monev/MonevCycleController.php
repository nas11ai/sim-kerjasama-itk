<?php

namespace App\Http\Controllers\Monev;

use App\Actions\Monev\OpenMonevCycle;
use App\Actions\Monev\SuggestCycleReviewers;
use App\Http\Controllers\Controller;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-MON-04, BR-MON-07, BR-MON-10 — Operator mengelola siklus monev.
 *
 * Membuka siklus aksi tersendiri, bukan efek samping tanggal yang tiba: jadwal
 * monev berjarak bulanan, dan pemberitahuannya menempel pada pembukaan itu.
 */
class MonevCycleController extends Controller
{
    public function show(FormSubmission $submission): Response
    {
        $this->authorizeManage();

        $submission->load('formPhaseDetail.formPhase');

        return Inertia::render('Monev/CyclePage', [
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
            ],
            'cycles' => $this->cyclesFor($submission),
            // BR-MON-07 — saran, bukan batas: Operator tetap bebas memilih nama
            // lain, dan daftar ini tidak pernah dipakai sebagai penyaring.
            'suggestedReviewers' => SuggestCycleReviewers::run($submission)
                ->map(fn (Reviewer $r): array => [
                    'id' => $r->id,
                    'name' => $r->user?->name,
                    'reviewer_type' => $r->reviewer_type,
                ])
                ->values(),
        ]);
    }

    public function open(Request $request, FormSubmission $submission): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'form_phase_detail_id' => 'required|exists:form_phase_details,id',
        ]);

        $diberitahu = OpenMonevCycle::run(
            $submission,
            FormPhaseDetail::findOrFail($validated['form_phase_detail_id']),
        );

        return back()->with(
            'success',
            $diberitahu === 0
                ? 'Siklus dibuka.'
                : "Siklus dibuka. {$diberitahu} orang diberi tahu."
        );
    }

    /**
     * Siklus milik Form pengajuan ini beserta keadaan laporannya.
     *
     * @return list<array{id: int, title: string, order: int, needs_review: bool, report_submitted: bool, can_proceed: bool}>
     */
    private function cyclesFor(FormSubmission $submission): array
    {
        $reports = FormSubmission::query()
            ->where('parent_submission_id', $submission->id)
            ->get()
            ->keyBy('form_phase_detail_id');

        $detail = $submission->formPhaseDetail;

        if ($detail === null) {
            return [];
        }

        return FormPhaseDetail::query()
            ->where('form_phase_id', $detail->form_phase_id)
            ->where('id', '!=', $detail->id)
            ->with('formPhase')
            ->ordered()
            ->get()
            ->map(function (FormPhaseDetail $cycle) use ($reports): array {
                $report = $reports->get($cycle->id);

                $phase = $cycle->formPhase;

                return [
                    'id' => $cycle->id,
                    'title' => $phase instanceof FormPhase ? $phase->title : 'Siklus #'.$cycle->id,
                    'order' => $cycle->order,
                    'needs_review' => $cycle->needs_review,
                    'report_submitted' => $report !== null && $report->is_submitted,
                    'can_proceed' => $report !== null && $report->canProceed(),
                ];
            })
            ->values()
            ->all();
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('submissions.view-all'), 403);
    }
}
