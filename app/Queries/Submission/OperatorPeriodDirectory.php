<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Queries\Reporting\SubmissionStatusBuckets;
use App\States\Submission\Approved;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar periode di layar Operator beserta jumlah pengajuan terkirimnya. Periode pengajuan
 * adalah FK-nya sendiri: fase boleh dimiliki banyak periode, jadi jalur lewat fase menghitung
 * pengajuan periode lain.
 *
 * @method static LengthAwarePaginator<int, SubmissionPeriod> run(?string $search)
 */
class OperatorPeriodDirectory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 10;

    /** @return LengthAwarePaginator<int, SubmissionPeriod> */
    public function handle(?string $search): LengthAwarePaginator
    {
        $page = SubmissionPeriod::query()
            ->with(['submissionDates.submissionDateLabel', 'submissionPeriodPhases.formPhase'])
            ->when($search !== null && $search !== '', fn ($q) => $q->whereLike('name', "%{$search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE);

        $counts = $this->countsFor($page->getCollection()->map(fn (SubmissionPeriod $p): int => $p->id)->all());

        return $page->through(function (SubmissionPeriod $period) use ($counts): SubmissionPeriod {
            $mine = $counts[$period->id] ?? ['total' => 0, 'approved' => 0, 'pending' => 0];
            $period->setAttribute('total_submissions', $mine['total']);
            $period->setAttribute('approved_submissions', $mine['approved']);
            $period->setAttribute('pending_review', $mine['pending']);

            return $period;
        });
    }

    /**
     * Menunggu review = kelompok `pending` dan `under_review`; yang sudah diputus tidak.
     *
     * @param  array<int, int>  $periodIds
     * @return array<int, array{total: int, approved: int, pending: int}>
     */
    private function countsFor(array $periodIds): array
    {
        $buckets = SubmissionStatusBuckets::run();
        $waiting = [...$buckets['pending'], ...$buckets['under_review']];
        $counts = [];

        $rows = FormSubmission::query()->toBase()
            ->where('is_submitted', true)
            ->whereIn('submission_period_id', $periodIds)
            ->select('submission_period_id', 'status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('submission_period_id', 'status')
            ->get();

        foreach ($rows as $row) {
            $id = is_numeric($row->submission_period_id) ? (int) $row->submission_period_id : 0;
            $total = is_numeric($row->total) ? (int) $row->total : 0;
            $counts[$id] ??= ['total' => 0, 'approved' => 0, 'pending' => 0];
            $counts[$id]['total'] += $total;
            $counts[$id]['approved'] += $row->status === Approved::$name ? $total : 0;
            $counts[$id]['pending'] += in_array($row->status, $waiting, true) ? $total : 0;
        }

        return $counts;
    }
}
