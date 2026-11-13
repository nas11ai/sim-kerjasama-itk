<?php

namespace App\Queries\Dashboard;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Dashboard\DashboardSectionData;
use App\Data\View\Dashboard\DeadlineData;
use App\Data\View\Dashboard\KpiCardData;
use App\Data\View\Review\AssignmentCardData;
use App\Models\Reviewer;
use App\Queries\Review\ReviewerAssignmentCards;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SHARED-02-02 — section "Review" dari penugasan yang masih berlaku (BR-REV-01, BR-REV-10).
 * Dihitung per `state`, bukan per keranjang: "perlu tindakan" juga memuat diskusi terbuka
 * yang evaluasinya sudah beres, dan itu bukan "perlu dievaluasi" (BR-MON-02).
 *
 * @method static array{section: DashboardSectionData, deadlines: list<DeadlineData>} run(Reviewer $reviewer)
 */
class ReviewerDashboardCards implements ChangesNoState
{
    use AsAction;

    /** @return array{section: DashboardSectionData, deadlines: list<DeadlineData>} */
    public function handle(Reviewer $reviewer): array
    {
        $cards = ReviewerAssignmentCards::run($reviewer);
        $all = [...$cards['needsAction'], ...$cards['done']];
        $pending = array_values(array_filter($all, static fn (AssignmentCardData $c): bool => $c->state === 'evaluation_pending'));
        $dated = $this->byDueDate($pending);
        $deadlines = array_map($this->deadline(...), $dated);
        $urgent = count(array_filter($deadlines, static fn (DeadlineData $d): bool => $d->urgent));
        $list = route('reviewer.assignments.index');

        return [
            'section' => new DashboardSectionData('reviewer', 'Review', [
                new KpiCardData('pending', 'Perlu Dievaluasi', strval(count($pending)), "{$urgent} mendekati deadline", $list),
                new KpiCardData('done', 'Evaluasi Selesai', strval(count($all) - count($pending)), 'Total', route('reviewer.history.index')),
                $this->nearest($dated[0] ?? null, $deadlines[0] ?? null),
            ]),
            'deadlines' => $deadlines,
        ];
    }

    /**
     * @param  list<AssignmentCardData>  $pending
     * @return list<AssignmentCardData>
     */
    private function byDueDate(array $pending): array
    {
        $dated = array_values(array_filter($pending, static fn (AssignmentCardData $c): bool => $c->dueDate !== null));
        usort($dated, static fn (AssignmentCardData $a, AssignmentCardData $b): int => strcmp($a->dueDate ?? '', $b->dueDate ?? ''));

        return $dated;
    }

    private function deadline(AssignmentCardData $card): DeadlineData
    {
        return DeadlineData::until(
            "Evaluasi: {$card->title}",
            Carbon::parse($card->dueDate),
            route('reviewer.assignments.evaluation', $card->submissionId),
        );
    }

    private function nearest(?AssignmentCardData $card, ?DeadlineData $deadline): KpiCardData
    {
        if ($card === null || $deadline === null) {
            return new KpiCardData('nearest', 'Deadline Terdekat', '-', null, null);
        }

        $value = match (true) {
            $deadline->daysLeft < 0 => 'Terlambat',
            $deadline->daysLeft === 0 => 'Hari ini',
            default => "{$deadline->daysLeft} hari",
        };

        return new KpiCardData('nearest', 'Deadline Terdekat', $value, $card->title, $deadline->href);
    }
}
