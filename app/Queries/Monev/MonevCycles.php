<?php

namespace App\Queries\Monev;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Monev\MonevCycleData;
use App\Enums\DeadlineType;
use App\Enums\MonevCycleState;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Queries\Submission\FirstSubmitAt;
use App\States\Submission\Approved;
use App\States\Submission\Withdrawn;
use App\Support\PhaseAccessGate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W125 — satu definisi siklus monev milik sebuah induk, dipakai gerbang (`MonevAccessGate`),
 * layar monev, dan Action penegaknya. Keadaannya turunan BR-MON-01/02/05/08, bukan kolom.
 *
 * @method static list<MonevCycleData> run(FormSubmission $parent)
 */
class MonevCycles implements ChangesNoState
{
    use AsAction;

    /** Ditulis `SubmitMonevReport`, dibaca di sini lewat `FirstSubmitAt`: satu sumber. */
    public const REPORT_SUBMITTED_AUDIT = 'Laporan monev disubmit';

    /**
     * Detail dalam tahap yang sama dengan detail induk dan ber-`order` lebih besar. Detail
     * pengajuan Form lain yang berbagi tahap (W92) berurutan lebih dulu, jadi tidak ikut.
     *
     * @return Collection<int, FormPhaseDetail>
     */
    public static function details(FormSubmission $parent): Collection
    {
        $own = $parent->formPhaseDetail;

        if ($own === null) {
            return new Collection;
        }

        return FormPhaseDetail::query()
            ->where('form_phase_id', $own->form_phase_id)
            ->where('order', '>', $own->order)
            // W139 — detail Upload Luaran milik luaran, bukan siklus monev.
            ->whereHas('submissionDate', fn (Builder $date) => $date->where('type', '!=', DeadlineType::Output->value))
            ->with(['submissionDate.submissionPeriod', 'phaseType', 'formAccessControl'])
            ->ordered()
            ->get();
    }

    /**
     * Padanan SQL `details()`: induk yang punya ≥ 1 siklus, supaya papan Operator bisa
     * dipaginasi di server tanpa menghitung siklus semua baris.
     *
     * @param  Builder<FormSubmission>  $parents
     * @return Builder<FormSubmission>
     */
    public static function whereHasCycles(Builder $parents): Builder
    {
        return $parents->whereHas('formPhaseDetail', fn (Builder $own) => $own->whereExists(
            fn (QueryBuilder $later) => $later->from('form_phase_details as later')
                ->whereColumn('later.form_phase_id', 'form_phase_details.form_phase_id')
                ->whereColumn('later.order', '>', 'form_phase_details.order')
                ->whereExists(fn (QueryBuilder $date) => $date->from('submission_dates')
                    ->whereColumn('submission_dates.id', 'later.submission_date_id')
                    ->where('submission_dates.type', '!=', DeadlineType::Output->value))
        ));
    }

    /** W125 — siklus tenggat Batas Laporan, lalu nama tipe tahap, lalu urutannya. */
    public static function nameOf(FormPhaseDetail $detail): string
    {
        $cycle = $detail->submissionDate->cycle;

        if ($cycle !== null) {
            return DeadlineType::cycleName($cycle);
        }

        return $detail->phaseType->name ?? "Tahap {$detail->order}";
    }

    /** W153 — siklus berjalan = siklus pertama yang belum Done; null bila semuanya selesai. */
    public static function current(FormSubmission $parent): ?MonevCycleData
    {
        return collect(self::run($parent))
            ->first(fn (MonevCycleData $cycle): bool => $cycle->state !== MonevCycleState::Done);
    }

    /** @return list<MonevCycleData> */
    public function handle(FormSubmission $parent): array
    {
        $reports = FormSubmission::query()
            ->where('parent_submission_id', $parent->id)
            ->addSelect(['form_submissions.*', 'first_submitted_at' => FirstSubmitAt::subquery(self::REPORT_SUBMITTED_AUDIT)])
            ->get()
            ->keyBy('form_phase_detail_id');

        $cycles = [];
        $previous = null;

        foreach (self::details($parent) as $detail) {
            $previous = $this->cycle($parent, $detail, $reports->get($detail->id), $previous);
            $cycles[] = $previous;
        }

        return $cycles;
    }

    private function cycle(
        FormSubmission $parent,
        FormPhaseDetail $detail,
        ?FormSubmission $report,
        ?MonevCycleData $previous,
    ): MonevCycleData {
        $name = self::nameOf($detail);
        [$state, $reason] = $this->stateOf($parent, $detail, $report, $previous);
        $submittedAt = $report?->getAttribute('first_submitted_at');

        return new MonevCycleData(
            detailId: $detail->id,
            name: $name,
            order: $detail->order,
            needsReview: $detail->needs_review,
            deadline: $detail->submissionDate->datetime->toIso8601String(),
            state: $state,
            blockedReason: $reason,
            reportId: $report?->id,
            submittedAt: is_string($submittedAt) ? CarbonImmutable::parse($submittedAt)->toIso8601String() : null,
            openedAt: $report?->cycle_opened_at?->toIso8601String(),
        );
    }

    /**
     * Diperiksa berurutan; yang pertama cocok menang. Laporan terkirim tetap terbaca
     * meski induknya ditarik (MON-CYCLE-11), jadi diperiksa sebelum pembekuan.
     *
     * @return array{0: MonevCycleState, 1: string|null}
     */
    private function stateOf(
        FormSubmission $parent,
        FormPhaseDetail $detail,
        ?FormSubmission $report,
        ?MonevCycleData $previous,
    ): array {
        $name = self::nameOf($detail);

        if ($report?->is_submitted === true) {
            return [$report->canProceed() ? MonevCycleState::Done : MonevCycleState::Submitted, null];
        }

        // BR-MON-08 — pembekuan mengalahkan segalanya.
        if ($parent->status instanceof Withdrawn) {
            return [MonevCycleState::Frozen, 'Pengajuan ini sudah ditarik. Siklus monevnya dibekukan dan tidak menerima laporan baru.'];
        }

        // BR-MON-01, BR-MON-02 — induk disetujui dan siklus sebelumnya tuntas.
        $locked = $this->lockedReason($parent, $previous, $name);

        if ($locked !== null) {
            return [MonevCycleState::Locked, $locked];
        }

        // BR-MON-05 — penerapan BR-FE-08: tenggat detail, atau override laporan ini.
        $open = $report === null ? $detail->isWithinDeadline() : PhaseAccessGate::isOpenFor($report, $detail);

        return $open
            ? [MonevCycleState::Open, null]
            : [MonevCycleState::Closed, "Batas waktu {$name} sudah lewat. Hubungi Operator LPPM bila membutuhkan perpanjangan."];
    }

    private function lockedReason(FormSubmission $parent, ?MonevCycleData $previous, string $name): ?string
    {
        if (!$parent->status instanceof Approved) {
            return 'Siklus monev terbuka setelah pengajuan disetujui.';
        }

        if ($previous !== null && $previous->state !== MonevCycleState::Done) {
            return "Selesaikan Laporan {$previous->name} lebih dulu sebelum mengisi {$name}.";
        }

        return null;
    }
}
