<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\Monev\SuggestCycleReviewers;
use App\Data\View\Monev\MonevCycleData;
use App\Data\View\Monev\MonevManagedCycleData;
use App\Data\View\SubmissionStatusData;
use App\Enums\MonevCycleState;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Models\User;
use App\Queries\Review\ManagedReviewers;
use App\Queries\Submission\OperatorSubmissionDetail;
use App\Support\PhaseAccessGate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-05-02 — siklus monev satu induk beserta reviewer, perpanjangan, dan kelayakan
 * aktivasi/penugasan. Keadaan siklus dari `MonevCycles` (W125), jadi layar dan gerbang sepakat.
 *
 * @phpstan-type Management array{submission: array{id: int, title: string, leader: string, status: SubmissionStatusData}, cycles: list<MonevManagedCycleData>, suggestedReviewerIds: list<int>}
 *
 * @method static Management run(User $viewer, FormSubmission $parent)
 */
class MonevCycleManagement implements ChangesNoState
{
    use AsAction;

    /**
     * Subjek penilaian kandidat reviewer satu siklus: laporannya, atau — sebelum laporan ada —
     * laporan bayangan yang tidak disimpan. Konflik dihitung atas tim induk (W124), jadi
     * kandidatnya sama dengan sesudah laporan dibuat; GET tidak membuat laporan.
     */
    public static function candidateSubject(FormSubmission $parent, FormPhaseDetail $detail): FormSubmission
    {
        return $parent->children()->where('form_phase_detail_id', $detail->id)->first()
            ?? $parent->children()->make([
                'submitted_by' => $parent->submitted_by,
                'form_phase_detail_id' => $detail->id,
            ]);
    }

    /** @return Management */
    public function handle(User $viewer, FormSubmission $parent): array
    {
        $parent->load('children');
        $cycles = [];
        $previous = null;

        foreach (MonevCycles::run($parent) as $cycle) {
            $cycles[] = $this->managed($viewer, $parent, $cycle, $previous);
            $previous = $cycle;
        }

        return [
            'submission' => [
                'id' => $parent->id,
                'title' => $parent->displayTitle(),
                'leader' => $parent->submittedBy->name,
                'status' => SubmissionStatusData::of($parent),
            ],
            'cycles' => $cycles,
            // BR-MON-07 — saran, bukan batas.
            'suggestedReviewerIds' => array_values(SuggestCycleReviewers::run($parent)
                ->map(fn (Reviewer $reviewer): int => $reviewer->id)
                ->all()),
        ];
    }

    private function managed(User $viewer, FormSubmission $parent, MonevCycleData $cycle, ?MonevCycleData $previous): MonevManagedCycleData
    {
        $report = $parent->children->firstWhere('id', $cycle->reportId);

        return new MonevManagedCycleData(
            cycle: $cycle,
            reviewers: $report === null ? [] : ManagedReviewers::run($report),
            phaseAccess: $report === null ? null : PhaseAccessGate::presentFor($report, $report->formPhaseDetail, true),
            overrideTarget: $report === null ? null : OperatorSubmissionDetail::overrideTargetFor($report, $report->getFormPhaseDetail()),
            canOpen: self::canOpen($cycle),
            openHint: self::openHint($cycle, $previous),
            canAssign: $viewer->can('assignMonevReviewer', $parent) && self::assignable($cycle),
            // BR-FE-27 — perpanjangan diberikan pada laporannya, selama belum terkirim.
            canExtend: $report !== null && !$report->is_submitted && $viewer->can('grantOverride', $report),
        );
    }

    /** BR-MON-10 — sekali (W126), selama siklus menunggu laporan; yang sudah masuk tak perlu diumumkan. */
    private static function canOpen(MonevCycleData $cycle): bool
    {
        return $cycle->openedAt === null && in_array($cycle->state, [MonevCycleState::Open, MonevCycleState::Closed], true);
    }

    private static function openHint(MonevCycleData $cycle, ?MonevCycleData $previous): ?string
    {
        if ($cycle->state !== MonevCycleState::Locked || $previous === null) {
            return null;
        }

        return "Aktivasi terbuka setelah siklus {$previous->name} selesai dievaluasi.";
    }

    /** BR-MON-04 — siklus yang belum terbuka (terkunci/dibekukan) atau sudah selesai tidak ditugasi. */
    private static function assignable(MonevCycleData $cycle): bool
    {
        return in_array($cycle->state, [MonevCycleState::Open, MonevCycleState::Closed, MonevCycleState::Submitted], true);
    }
}
