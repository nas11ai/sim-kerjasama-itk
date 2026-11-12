<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionReviewer;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Resubmitted;
use App\States\Submission\Withdrawn;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-19 — jumlah pengajuan angkatan × langkah ini yang sudah diputuskan (versi baru tak boleh
 * diterapkan lagi). Pengajuan: disetujui (termasuk yang lalu ditarik), ditolak, atau revisi diminta. Monev: laporan disubmit
 * yang semua reviewer aktifnya selesai tanpa catatan terbuka (padanan SQL `canProceed()`).
 *
 * @method static int run(SubmissionPeriod $period, FormPhaseDetail $detail)
 */
class DecidedSubmissionsAtStep implements ChangesNoState
{
    use AsAction;

    // Withdrawn hanya bisa dicapai dari Approved, jadi ia tetap pengajuan yang sudah diputuskan.
    private const DECIDED = [Approved::class, Rejected::class, NeedsRevision::class, Resubmitted::class, Withdrawn::class];

    public function handle(SubmissionPeriod $period, FormPhaseDetail $detail): int
    {
        $query = FormSubmission::query()
            ->where('submission_period_id', $period->id)
            ->where('form_phase_detail_id', $detail->id);

        return $detail->deadline_cycle === null
            ? $query->whereIn('status', array_map(fn (string $state): string => $state::$name, self::DECIDED))->count()
            : $this->settledReports($query)->count();
    }

    /**
     * Laporan tanpa reviewer aktif belum dinilai siapa pun, jadi belum diputuskan — berbeda dari
     * `canProceed()` yang meloloskannya supaya siklus berikutnya tidak terkunci.
     *
     * @param  Builder<FormSubmission>  $reports
     * @return Builder<FormSubmission>
     */
    private function settledReports(Builder $reports): Builder
    {
        return $reports
            ->where('is_submitted', true)
            ->whereHas('submissionReviewers', fn (Builder $seat) => $seat->where('status', 'active'))
            ->whereDoesntHave('submissionReviewers', fn (Builder $seat) => $seat
                ->where('status', 'active')
                ->whereNotIn('evaluation_status', SubmissionReviewer::SETTLED_EVALUATION))
            ->whereDoesntHave('reviewSummaries', fn (Builder $summary) => $summary->where('status', 'open'));
    }
}
