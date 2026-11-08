<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\StatisticsFilters;
use App\Data\View\Reporting\FollowUpItemData;
use App\Data\View\Reporting\FollowUpListData;
use App\Models\FormSubmission;
use App\Queries\Submission\FirstSubmitAt;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Submitted;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W152 — tab Tindak Lanjut: tiga daftar tindakan berikutnya dari basis W149, terlama dulu.
 * Umur adalah fakta, bukan ambang SLA (aturan bisnis yang tidak ada di DDD).
 *
 * @method static list<FollowUpListData> run(StatisticsFilters $filters, bool $withItems)
 */
class FollowUpLists implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 5;

    private const WITHOUT_OUTPUT = 'approvedWithoutOutput';

    /** @var array<string, array{class-string<SubmissionStatus>, string, string}> kunci → state, judul, hint */
    private const LISTS = [
        'awaitingReviewer' => [Submitted::class, 'Menunggu Penugasan Reviewer', 'Tugaskan reviewer dari detail pengajuan.'],
        'awaitingRevision' => [NeedsRevision::class, 'Menunggu Perbaikan Peneliti', 'Peneliti sedang memperbaiki; perpanjang batas bila diminta.'],
        self::WITHOUT_OUTPUT => [Approved::class, 'Disetujui tanpa Luaran', 'Ingatkan tim untuk mendaftarkan luaran.'],
    ];

    /** @return list<FollowUpListData> */
    public function handle(StatisticsFilters $filters, bool $withItems): array
    {
        $lists = [];

        foreach (self::LISTS as $key => [$state, $title, $hint]) {
            $query = $this->query($filters, $key, $state);
            // Daftar Operator tidak punya filter luaran (YAGNI), jadi kartu itu tanpa tautan daftar.
            $linked = $withItems && $key !== self::WITHOUT_OUTPUT;

            $lists[] = new FollowUpListData(
                key: $key,
                title: $title,
                hint: $hint,
                count: $query->count(),
                // BR-SM-07 — judul dan nama ketua hanya bagi submissions.view-all (W148).
                items: $withItems ? $this->items($query) : [],
                href: $linked ? route('admin.submissions.index', ['status' => $state::getMorphClass(), ...$filters->withoutYear()->query()]) : null,
            );
        }

        return $lists;
    }

    /**
     * @param  class-string<SubmissionStatus>  $state
     * @return Builder<FormSubmission>
     */
    private function query(StatisticsFilters $filters, string $key, string $state): Builder
    {
        return ReportingSubmissions::base($filters, withYear: false)
            ->where('form_submissions.status', $state::getMorphClass())
            ->when($key === self::WITHOUT_OUTPUT, fn (Builder $q) => $q->doesntHave('researchOutputs'));
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @return list<FollowUpItemData>
     */
    private function items(Builder $query): array
    {
        $submissions = $query
            ->addSelect(['form_submissions.*', 'first_submitted_at' => FirstSubmitAt::subquery()])
            ->with(['form:id,title', 'submittedBy:id,name', 'scheme:id,name'])
            ->orderBy('first_submitted_at')
            ->orderBy('form_submissions.id')
            ->limit(self::LIMIT)
            ->get();

        return array_values($submissions->map(fn (FormSubmission $submission): FollowUpItemData => new FollowUpItemData(
            id: $submission->id,
            title: $submission->form->title,
            leader: $submission->submittedBy->name,
            scheme: $submission->scheme?->name,
            ageDays: $this->ageDays($submission),
            href: route('admin.submissions.show', $submission),
        ))->all());
    }

    private function ageDays(FormSubmission $submission): int
    {
        $at = $submission->getAttribute('first_submitted_at');

        return is_string($at) ? FirstSubmitAt::ageDays($at) : 0;
    }
}
