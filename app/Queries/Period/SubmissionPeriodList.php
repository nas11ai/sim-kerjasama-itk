<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\PeriodRowData;
use App\Enums\DeadlineType;
use App\Enums\PeriodStatus;
use App\Models\FormPhase;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Support\Search\LikePattern;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-09-01 — seluruh periode, tersaring di server; nilai filter tak dikenal diabaikan.
 *
 * @phpstan-type Filters array{search: string, status: string, year: string}
 * @phpstan-type Option array{value: string, label: string}
 * @phpstan-type Result array{periods: LengthAwarePaginator<int, PeriodRowData>, statusOptions: list<Option>, yearOptions: list<Option>, typeOptions: list<Option>, phaseOptions: list<Option>}
 *
 * @method static Result run(Filters $filters)
 */
class SubmissionPeriodList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /**
     * @param  Filters  $filters
     * @return Result
     */
    public function handle(array $filters): array
    {
        $query = SubmissionPeriod::query()
            ->with([
                'submissionDates' => fn (Relation $dates) => $dates->addSelect(['needing_step_count' => SubmissionDate::needingStepCount()]),
                'submissionPeriodPhases',
            ])
            // Predikat yang sama dengan Semua Pengajuan (OperatorSubmissionList): induk yang terkirim.
            ->withCount(['formSubmissions as submitted_count' => fn (Builder $q) => $q
                ->where('is_submitted', true)
                ->whereNull('parent_submission_id')])
            ->latest('id');

        $this->filter($query, $filters);

        $page = $query->paginate(self::PER_PAGE)->withQueryString();
        $unready = $this->unreadyCohortCounts(array_values($page->items()));

        return [
            'periods' => $page->through(fn (SubmissionPeriod $period): PeriodRowData => PeriodRowData::fromModel($period, $unready[$period->id] ?? 0)),
            'statusOptions' => array_map(fn (PeriodStatus $s): array => $s->option(), PeriodStatus::cases()),
            'yearOptions' => $this->yearOptions(),
            'typeOptions' => array_map(
                fn (DeadlineType $type): array => ['value' => $type->value, 'label' => $type->label()],
                DeadlineType::cases(),
            ),
            'phaseOptions' => $this->phaseOptions(),
        ];
    }

    /**
     * BR-FE-36, G4-W7 — angkatan `blocked` + `needs_fix` per periode, satu penilaian untuk satu halaman.
     * Seperti kartu fase: periode yang tutup sepenuhnya hanya menghitung angkatan berjalan.
     *
     * @param  list<SubmissionPeriod>  $periods
     * @return array<int, int>
     */
    private function unreadyCohortCounts(array $periods): array
    {
        $closed = [];
        foreach ($periods as $period) {
            $closed[$period->id] = SubmissionPeriodGate::isFullyClosed($period);
        }

        $counts = [];
        foreach (CohortReadiness::run(array_keys($closed)) as $cohort) {
            if ($cohort->hasBlockingIssues() && ($cohort->running || !($closed[$cohort->periodId] ?? false))) {
                $counts[$cohort->periodId] = ($counts[$cohort->periodId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  Builder<SubmissionPeriod>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] !== '', fn (Builder $q) => $q->whereLike('name', LikePattern::contains($filters['search'])));

        $year = $filters['year'];
        $query->when(ctype_digit($year), fn (Builder $q) => $q->whereHas('submissionDates', fn (Builder $d) => $d->whereYear('datetime', $year)));

        $status = PeriodStatus::tryFrom($filters['status']);

        if ($status !== null) {
            $this->whereStatus($query, $status);
        }
    }

    /**
     * Cermin SQL `SubmissionPeriodGate::displayStatus` — test membandingkan keduanya.
     *
     * @param  Builder<SubmissionPeriod>  $query
     */
    private function whereStatus(Builder $query, PeriodStatus $status): void
    {
        if ($status === PeriodStatus::ForceClosed) {
            $query->where('is_force_closed', true);

            return;
        }

        // K8d — status dibaca dari Batas Submit siklus utama, sama dengan SubmissionPeriodGate.
        $submit = static fn (Builder $d): Builder => $d->where('type', DeadlineType::Submit->value)->whereNull('cycle');
        $query->where('is_force_closed', false);

        match ($status) {
            PeriodStatus::NoDates => $query->whereDoesntHave('submissionDates', $submit),
            PeriodStatus::Active => $query->whereHas('submissionDates', fn (Builder $d) => $submit($d)->where('datetime', '>', now())),
            default => $query->whereHas('submissionDates', fn (Builder $d) => $submit($d)->where('datetime', '<=', now())),
        };
    }

    /** @return list<Option> W74 — tahun salah satu tenggat, terbaru dulu. */
    private function yearOptions(): array
    {
        $years = SubmissionDate::query()->get(['datetime'])
            ->map(fn (SubmissionDate $date): string => $date->datetime->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        return array_values($years->map(fn (string $y): array => ['value' => $y, 'label' => $y])->all());
    }

    /**
     * Tahap aktif ditambah tahap nonaktif yang sudah melekat ke suatu periode, supaya
     * menyunting periode tidak diam-diam melepas tahap lamanya. BR-FE-33 — hanya tahap terbit.
     *
     * @return list<Option>
     */
    private function phaseOptions(): array
    {
        return array_values(FormPhase::query()
            ->whereHas('versions')
            ->where(fn (Builder $q) => $q->where('is_active', true)
                ->orWhereIn('id', SubmissionPeriodPhase::query()->select('form_phase_id')))
            ->orderBy('title')
            ->get(['id', 'title', 'is_active'])
            ->map(fn (FormPhase $phase): array => [
                'value' => (string) $phase->id,
                'label' => $phase->is_active ? $phase->title : $phase->title.' (nonaktif)',
            ])
            ->all());
    }
}
