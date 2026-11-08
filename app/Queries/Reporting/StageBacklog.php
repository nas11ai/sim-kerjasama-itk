<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\StageBacklogData;
use App\Models\FormSubmission;
use App\Queries\Submission\FirstSubmitAt;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Resubmitted;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W152 — "Pengajuan Berjalan": tahap yang belum diputuskan, urut alur, beserta umur terlama
 * sejak submit pertama (fakta, bukan SLA). Keadaan sekarang, jadi filter Tahun diabaikan.
 *
 * @method static list<StageBacklogData> run(StatisticsFilters $filters, bool $withLinks)
 */
class StageBacklog implements ChangesNoState
{
    use AsAction;

    /** @var list<class-string<SubmissionStatus>> */
    public const RUNNING = [Submitted::class, UnderReview::class, NeedsRevision::class, Resubmitted::class];

    /** @return list<string> isi kolom `status` keempat tahap berjalan */
    public static function runningKeys(): array
    {
        return array_map(fn (string $class): string => $class::getMorphClass(), self::RUNNING);
    }

    /** @return list<StageBacklogData> */
    public function handle(StatisticsFilters $filters, bool $withLinks): array
    {
        $rows = $this->rows($filters);

        return array_map(function (string $class) use ($rows, $filters, $withLinks): StageBacklogData {
            $state = new $class(new FormSubmission);
            [$count, $oldest] = $rows[$state->key()] ?? [0, null];

            return new StageBacklogData(
                key: $state->key(),
                label: $state->label(),
                count: $count,
                oldestAgeDays: $oldest === null ? null : (int) floor(CarbonImmutable::parse($oldest)->diffInDays(now())),
                href: $withLinks ? route('admin.submissions.index', ['status' => $state->key(), ...$filters->withoutYear()->query()]) : null,
            );
        }, self::RUNNING);
    }

    /** @return array<string, array{int, ?string}> jumlah dan submit pertama terlama per `status` */
    private function rows(StatisticsFilters $filters): array
    {
        $submissions = ReportingSubmissions::base($filters, withYear: false)
            ->whereIn('form_submissions.status', self::runningKeys())
            ->select(['form_submissions.status', 'first_submit' => FirstSubmitAt::subquery()]);
        $found = DB::query()->fromSub($submissions, 'running')
            ->select('status')
            ->selectRaw('COUNT(*) AS total, MIN(first_submit) AS oldest')
            ->groupBy('status')
            ->get();
        $rows = [];

        foreach ($found as $row) {
            if (is_string($row->status)) {
                $rows[$row->status] = [is_numeric($row->total) ? (int) $row->total : 0, is_string($row->oldest) ? $row->oldest : null];
            }
        }

        return $rows;
    }
}
