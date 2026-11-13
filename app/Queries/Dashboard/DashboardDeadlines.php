<?php

namespace App\Queries\Dashboard;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Dashboard\DeadlineData;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Submission\ResearcherStartOptions;
use App\Support\Format\PeriodName;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Deadline Mendekat" (W45) — satu daftar gabungan, urut tenggat, maks 5. Periode terbuka
 * dibaca lewat SubmissionPeriodGate (BR-FE-01); yang tertutup tidak menagih apa pun.
 *
 * @method static list<DeadlineData> run(User $user, list<DeadlineData> $reviewerDeadlines)
 */
class DashboardDeadlines implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 5;

    /**
     * @param  list<DeadlineData>  $reviewerDeadlines
     * @return list<DeadlineData>
     */
    public function handle(User $user, array $reviewerDeadlines): array
    {
        // Diindeks per periode: pemegang dua permission tidak melihat periode yang sama dua kali.
        $periods = $this->researcherPeriods($user) + $this->managedPeriods($user);
        $all = [...array_values($periods), ...$reviewerDeadlines];

        usort($all, static fn (DeadlineData $a, DeadlineData $b): int => Carbon::parse($a->closesAt) <=> Carbon::parse($b->closesAt));

        return array_slice($all, 0, self::LIMIT);
    }

    /** @return array<int, DeadlineData> */
    private function researcherPeriods(User $user): array
    {
        if (!$user->can('submissions.create')) {
            return [];
        }

        $deadlines = [];

        foreach (ResearcherStartOptions::run($user) as $option) {
            if ($option->closesAt !== null) {
                $deadlines[$option->id] = DeadlineData::until(
                    PeriodName::format($option->name).' ditutup',
                    Carbon::parse($option->closesAt),
                    route('user.submissions.create'),
                );
            }
        }

        return $deadlines;
    }

    /** @return array<int, DeadlineData> */
    private function managedPeriods(User $user): array
    {
        if (!$user->can('periods.manage')) {
            return [];
        }

        $deadlines = [];
        $periods = SubmissionPeriod::query()->with('submissionDates')->get()
            ->filter(static fn (SubmissionPeriod $period): bool => SubmissionPeriodGate::isOpen($period));

        foreach ($periods as $period) {
            // K8d — periode berhenti menerima pengajuan baru pada Batas Submit siklus utamanya.
            $closesAt = SubmissionPeriodGate::submitDeadline($period);

            if ($closesAt instanceof SubmissionDate) {
                $deadlines[$period->id] = DeadlineData::until(
                    'Tutup '.PeriodName::format($period->name),
                    $closesAt->datetime,
                    route('admin.submission-periods.index'),
                );
            }
        }

        return $deadlines;
    }
}
