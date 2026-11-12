<?php

namespace App\Actions\Review;

use App\Models\EvaluationFormPin;
use App\Models\ReviewEvaluationFormVersion;
use App\Models\ReviewFormResponse;
use App\Models\StepEvaluationForm;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Notifications\EvaluationVersionAppliedNotification;
use App\Queries\Review\DecidedSubmissionsAtStep;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-19 — Operator menerapkan versi terbaru Form Evaluasi ke satu angkatan × langkah. Jawaban
 * yang ada (draf maupun terkirim) menjadi riwayat, reviewer-nya mengisi ulang versi baru dan diberi
 * tahu. Ditolak bila sudah ada pengajuan langkah itu yang diputuskan; tidak ada transisi status.
 *
 * @method static int run(EvaluationFormPin $pin, User $actor)
 */
class ApplyLatestEvaluationVersion
{
    use AsAction;

    /**
     * @return int jumlah jawaban yang dibuka ulang
     *
     * @throws ValidationException
     */
    public function handle(EvaluationFormPin $pin, User $actor): int
    {
        [$seats, $version, $reopened] = DB::transaction(fn (): array => $this->apply($pin, $actor));

        // Sesudah commit: penolakan atau kegagalan tidak meninggalkan kabar palsu.
        foreach ($seats as $seat) {
            Deliver::to($seat->reviewer->user, new EvaluationVersionAppliedNotification($seat->formSubmission, $pin->reviewEvaluationForm, $version));
        }

        return $reopened;
    }

    /**
     * Baris pin dikunci: dua Operator yang menerapkan bersamaan tidak menggandakan riwayat.
     *
     * @return array{0: Collection<int, SubmissionReviewer>, 1: ReviewEvaluationFormVersion, 2: int}
     *
     * @throws ValidationException
     */
    private function apply(EvaluationFormPin $pin, User $actor): array
    {
        $locked = EvaluationFormPin::query()->whereKey($pin->id)->lockForUpdate()->firstOrFail();
        $latest = $locked->reviewEvaluationForm->latestVersion()->firstOrFail();
        $this->guardApplicable($locked, $latest);

        $previous = $locked->reviewEvaluationFormVersion;
        $responses = ReviewFormResponse::query()
            ->currentInCohortOf($locked)
            ->with('reviewerFormAssignment.submissionReviewer.reviewer.user', 'reviewerFormAssignment.submissionReviewer.formSubmission')
            ->get();

        ReviewFormResponse::query()->whereKey($responses->modelKeys())->update(['superseded_at' => now()]);
        $locked->update(['review_evaluation_form_version_id' => $latest->id, 'pinned_at' => now()]);
        // B10 — keputusan tidak mengunci pin; yang lolos sesudah cek pertama menggulung Apply ini.
        $this->assertUndecided($locked);
        $seats = $this->reopenSeats($responses);

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Versi baru form evaluasi diterapkan', $locked, $actor, new AuditChange(
            old: ['version' => $previous->number],
            new: ['version' => $latest->number, 'reopened' => $responses->count()],
        ));

        return [$seats, $latest, $responses->count()];
    }

    /** @throws ValidationException */
    private function guardApplicable(EvaluationFormPin $pin, ReviewEvaluationFormVersion $latest): void
    {
        if ($pin->review_evaluation_form_version_id === $latest->id) {
            $this->reject('Angkatan ini sudah memakai versi terbaru.');
        }

        // B10 — layar hanya menampilkan pemasangan aktif di langkah direview (CohortEvaluationVersions).
        $shown = StepEvaluationForm::query()->active()
            ->where('form_phase_detail_id', $pin->form_phase_detail_id)
            ->where('review_evaluation_form_id', $pin->review_evaluation_form_id)
            ->whereHas('formPhaseDetail', fn (Builder $detail) => $detail->where('needs_review', true))
            ->exists();

        if (!$shown) {
            $this->reject('Form evaluasi ini tidak aktif di langkah ini, jadi versinya tidak diterapkan.');
        }

        $this->assertUndecided($pin);
    }

    /**
     * BR-REV-19 — keputusan yang sudah diambil tidak dibatalkan, jadi angkatannya tidak dibuka ulang.
     *
     * @throws ValidationException
     */
    private function assertUndecided(EvaluationFormPin $pin): void
    {
        $decided = DecidedSubmissionsAtStep::run($pin->submissionPeriod, $pin->formPhaseDetail);

        if ($decided > 0) {
            $this->reject("Tidak bisa diterapkan: {$decided} pengajuan di langkah ini sudah diputuskan.");
        }
    }

    /**
     * Kursi pemilik jawaban yang dibuka ulang: status evaluasinya kembali menunggu dan tanda Selesai
     * di putarannya dicabut (BR-REV-05, BR-REV-16).
     *
     * @param  EloquentCollection<int, ReviewFormResponse>  $responses
     * @return Collection<int, SubmissionReviewer>
     */
    private function reopenSeats(EloquentCollection $responses): Collection
    {
        $seats = $responses
            ->map(fn (ReviewFormResponse $response): SubmissionReviewer => $response->reviewerFormAssignment->submissionReviewer)
            ->unique('id')
            ->values()
            ->toBase();

        foreach ($seats as $seat) {
            RecalculateEvaluationStatus::run($seat);
            ReopenRoundSeat::run($seat);
        }

        return $seats;
    }

    /** @throws ValidationException */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['pin' => $message]);
    }
}
