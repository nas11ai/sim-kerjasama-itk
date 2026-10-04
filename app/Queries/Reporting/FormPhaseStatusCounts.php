<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use stdClass;

/**
 * BR-RPT-06 — rincian status pengajuan per fase: satu kueri GROUP BY (fase, state) yang
 * dilipat ke kelompok SubmissionStatusBuckets. Draft tidak masuk kelompok mana pun,
 * jadi `total_submissions` sama dengan jumlah semua kelompok.
 *
 * @method static Collection<int, stdClass> run()
 */
class FormPhaseStatusCounts implements ChangesNoState
{
    use AsAction;

    /** @return Collection<int, stdClass> */
    public function handle(): Collection
    {
        $buckets = SubmissionStatusBuckets::run();
        $bucketOf = $this->bucketOfState($buckets);
        $zero = array_fill_keys(['total_submissions', ...array_keys($buckets)], 0);
        $titles = [];
        $counts = [];

        foreach ($this->rows() as $row) {
            $id = is_numeric($row->id) ? (int) $row->id : 0;
            $total = is_numeric($row->total) ? (int) $row->total : 0;
            $bucket = is_string($row->status) ? ($bucketOf[$row->status] ?? null) : null;

            $titles[$id] = $row->title;
            $counts[$id] ??= $zero;

            if ($bucket === null) {
                continue;
            }

            $counts[$id][$bucket] += $total;
            $counts[$id]['total_submissions'] += $total;
        }

        $phases = [];

        foreach ($counts as $id => $phaseCounts) {
            $phases[] = (object) ['id' => $id, 'title' => $titles[$id], ...$phaseCounts];
        }

        return collect($phases);
    }

    /**
     * @param  array<string, list<string>>  $buckets
     * @return array<string, string> nama state => kelompok
     */
    private function bucketOfState(array $buckets): array
    {
        $bucketOf = [];

        foreach ($buckets as $bucket => $states) {
            foreach ($states as $state) {
                $bucketOf[$state] = $bucket;
            }
        }

        return $bucketOf;
    }

    /** @return Collection<int, stdClass> */
    private function rows(): Collection
    {
        // Subquery DISTINCT supaya satu pengajuan tidak terhitung dua kali bila form-nya
        // terpasang di beberapa akses.
        return DB::query()->fromRaw('(
            SELECT DISTINCT
                form_phases.id AS phase_id,
                form_phases.title AS phase_title,
                form_submissions.id AS submission_id,
                form_submissions.status
            FROM form_phases
            LEFT JOIN form_phase_details ON form_phase_details.form_phase_id = form_phases.id
            LEFT JOIN form_access_controls ON form_access_controls.id = form_phase_details.form_access_control_id
            LEFT JOIN forms ON forms.id = form_access_controls.form_id
            LEFT JOIN form_submissions ON form_submissions.form_id = forms.id
        ) as distinct_subs')
            ->select('phase_id as id', 'phase_title as title', 'status')
            ->selectRaw('COUNT(submission_id) as total')
            ->groupBy('phase_id', 'phase_title', 'status')
            ->orderBy('phase_id')
            ->get();
    }
}
