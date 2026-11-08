<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\StatusShareData;
use App\Models\FormSubmission;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use App\States\Submission\Withdrawn;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W151 — "Submission per Status": ketujuh state terkirim menurut urutan alur, masing-masing
 * dengan pangsa dari total dan tautan ke daftar Operator berfilter yang sama (W149).
 *
 * @method static list<StatusShareData> run(StatisticsFilters $filters, bool $withLinks)
 */
class StatusBreakdown implements ChangesNoState
{
    use AsAction;

    /** @var list<class-string<SubmissionStatus>> */
    private const FLOW = [
        Submitted::class, UnderReview::class, NeedsRevision::class, Resubmitted::class,
        Approved::class, Rejected::class, Withdrawn::class,
    ];

    /** @return list<StatusShareData> */
    public function handle(StatisticsFilters $filters, bool $withLinks): array
    {
        $counts = $this->counts($filters);
        $total = array_sum($counts);

        return array_map(function (string $class) use ($counts, $total, $filters, $withLinks): StatusShareData {
            $state = new $class(new FormSubmission);
            $count = $counts[$state->key()] ?? 0;

            return new StatusShareData(
                key: $state->key(),
                label: $state->label(),
                variant: $state->variant(),
                count: $count,
                share: $total === 0 ? 0.0 : $count / $total,
                href: $withLinks ? route('admin.submissions.index', ['status' => $state->key(), ...$filters->query()]) : null,
            );
        }, self::FLOW);
    }

    /** @return array<string, int> jumlah per isi kolom `status` */
    private function counts(StatisticsFilters $filters): array
    {
        $rows = ReportingSubmissions::base($filters)->toBase()
            ->select('form_submissions.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('form_submissions.status')
            ->get();
        $counts = [];

        foreach ($rows as $row) {
            if (is_string($row->status)) {
                $counts[$row->status] = is_numeric($row->total) ? (int) $row->total : 0;
            }
        }

        return $counts;
    }
}
