<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Monev\MonevCycleData;
use App\Data\View\Monev\MonevReportHeaderData;
use App\Data\View\Submission\Revision\ReviewThreadData;
use App\Data\View\Submission\SubmissionAnswerData;
use App\Data\View\SubmissionStatusData;
use App\Enums\MonevCycleState;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Review\ReviewThreads;
use App\Queries\Review\SubmittedEvaluationSheets;
use App\Queries\Review\VisibleReviewerScores;
use App\Queries\Submission\ReadableSubmissionAnswers;
use App\States\Submission\Withdrawn;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Props Monev/ReportShowPage (RESEARCHER-05-03). Akses (`view`, BR-SM-07) dijaga controller;
 * skor mengikuti BR-MON-09 lewat VisibleReviewerScores.
 *
 * @phpstan-import-type Score from SubmittedEvaluationSheets
 *
 * @phpstan-type ReportDetail array{report: MonevReportHeaderData, cycle: MonevCycleData, status: SubmissionStatusData, answers: list<SubmissionAnswerData>, scores: array{visible: bool, reason: string|null, scores: list<Score>}, threads: list<ReviewThreadData>, parentWithdrawn: bool, can: array{fill: bool, reply: bool, resolveThreads: bool}}
 *
 * @method static ReportDetail run(User $viewer, FormSubmission $report)
 */
class MonevReportDetail implements ChangesNoState
{
    use AsAction;

    /** @return ReportDetail */
    public function handle(User $viewer, FormSubmission $report): array
    {
        $parent = $report->parent()->with(['form:id,title', 'scheme:id,name', 'formPhaseDetail'])->firstOrFail();
        $cycle = MonevReportForm::cycleOf($parent, $report);
        $threads = ReviewThreads::run($report);

        return [
            'report' => MonevReportHeaderData::of($report, $parent),
            'cycle' => $cycle,
            'status' => SubmissionStatusData::of($report),
            'answers' => ReadableSubmissionAnswers::run($report),
            'scores' => VisibleReviewerScores::run($viewer, $report),
            'threads' => ReviewThreadData::many($threads['summaries'], $threads['comments']),
            // BR-MON-08 — laporan terkirim tetap terbaca sebagai arsip (MON-CYCLE-11).
            'parentWithdrawn' => $parent->status instanceof Withdrawn,
            'can' => [
                'fill' => !$report->is_submitted && $cycle->state === MonevCycleState::Open && $viewer->can('update', $report),
                // BR-REV-06 — dari pihak peneliti hanya ketua yang berdiskusi.
                'reply' => $viewer->can('discussReview', $report),
                // Reviewer menutup ulirnya dari ringkasan review-nya sendiri, bukan dari sini.
                'resolveThreads' => false,
            ],
        ];
    }
}
