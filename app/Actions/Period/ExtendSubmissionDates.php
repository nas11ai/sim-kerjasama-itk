<?php

namespace App\Actions\Period;

use App\Data\Request\PeriodDateInput;
use App\Data\Result\DeadlineExtension;
use App\Enums\DeadlineType;
use App\Models\ReviewerFormAssignment;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Queries\Period\PeriodDraftOwners;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-08 — memberi tahu pemilik draft kalau Batas Submit/Revisi bergeser LEBIH LAMA;
 * memajukan, atau menggeser Batas Evaluasi, tidak memberi peneliti kesempatan baru.
 * K8e — tenggat yang digeser berlaku untuk semua, termasuk penugasan reviewer yang sudah ada.
 *
 * @method static DeadlineExtension run(SubmissionPeriod $period, list<PeriodDateInput> $dates, ?User $actor = null)
 */
class ExtendSubmissionDates
{
    use AsAction;

    /**
     * @param  list<PeriodDateInput>  $dates
     */
    public function handle(SubmissionPeriod $period, array $dates, ?User $actor = null): DeadlineExtension
    {
        return DB::transaction(fn (): DeadlineExtension => $this->extend($period, $dates, $actor));
    }

    /** @param  list<PeriodDateInput>  $dates */
    private function extend(SubmissionPeriod $period, array $dates, ?User $actor): DeadlineExtension
    {
        // Mengirim untuk setiap penyuntingan membuat notifikasi ini cepat
        // diabaikan, dan yang hilang justru yang penting.
        if (!$this->syncDates($period, $dates)) {
            return new DeadlineExtension(extended: false, notified: 0);
        }

        $pemilikDraft = $this->recipients($period);

        // SCH-RULE-13 — tanpa draft berjalan, tidak ada yang perlu diberi tahu.
        if ($pemilikDraft->isEmpty()) {
            return new DeadlineExtension(extended: true, notified: 0);
        }

        // BR-RPT-09 — revisi periode bisa menulis dua entri berbeda: periode
        // direvisi dan tenggat diperpanjang.
        AuditTrail::record('Tenggat periode diperpanjang', $period, $actor);

        // Transaksi luar (ReviseSubmissionPeriod) bisa menggulung semuanya, jadi kirim
        // sesudah commit. ChannelNotDeclared (BR-NOT-06) muncul di sini, sesudah commit:
        // deklarasi kanal wajib sudah ada.
        DB::afterCommit(static function () use ($pemilikDraft, $period): void {
            Deliver::to($pemilikDraft, new SubmissionDeadlineExtendedNotification($period));
        });

        return new DeadlineExtension(extended: true, notified: $pemilikDraft->count());
    }

    /**
     * W73 — draft di periode yang ditutup Operator terarsip (BR-SM-13) dan tetap
     * terkunci (BR-SCH-07): bukan DRAFT aktif, jadi tidak ada yang diberi tahu.
     *
     * @return Collection<int, User>
     */
    private function recipients(SubmissionPeriod $period): Collection
    {
        return $period->is_force_closed ? new Collection : PeriodDraftOwners::run($period);
    }

    /**
     * Menyelaraskan tanggal periode dengan daftar masukan.
     *
     * @param  list<PeriodDateInput>  $dates
     * @return bool apakah ada batas yang mundur
     */
    private function syncDates(SubmissionPeriod $period, array $dates): bool
    {
        $existing = $this->datesByKey($period);

        $adaPerpanjangan = false;
        $terpakai = [];

        foreach ($dates as $row) {
            $baru = Carbon::parse($row->date);
            $lama = $existing->get($row->key());

            if ($lama instanceof SubmissionDate) {
                $adaPerpanjangan = $this->move($lama, $baru) || $adaPerpanjangan;
                $terpakai[] = $lama->id;

                continue;
            }

            $terpakai[] = SubmissionDate::create([
                'submission_period_id' => $period->id,
                'type' => $row->type,
                'cycle' => $row->cycle,
                'datetime' => $baru,
            ])->id;
        }

        $this->deleteUnlisted($period, $terpakai);

        return $adaPerpanjangan;
    }

    /**
     * Menggeser satu tanggal di tempat. Hapus-lalu-buat-ulang gagal di periode terpakai: foreign
     * key `form_phase_details` menunjuknya, dan bila lolos, tiap detail kehilangan tanggalnya.
     *
     * @return bool apakah pemilik draft perlu diberi tahu (BR-SCH-08)
     */
    private function move(SubmissionDate $date, Carbon $to): bool
    {
        $later = $to->gt($date->datetime);

        if ($date->type === DeadlineType::Evaluation) {
            $this->followEvaluation($date, $to);
        }

        $date->update(['datetime' => $to]);

        return $later && $date->type !== DeadlineType::Evaluation;
    }

    /**
     * K8e — penugasan menyimpan salinan Batas Evaluasi (`due_date`, EvaluationDueDate). Salinan
     * yang masih sama dengan tanggal lama ikut bergeser; tenggat yang disetel tangan dibiarkan.
     */
    private function followEvaluation(SubmissionDate $date, Carbon $to): void
    {
        ReviewerFormAssignment::query()
            ->where('due_date', $date->datetime)
            ->whereHas('reviewEvaluationForm.formPhaseDetail', fn (Builder $q) => $q->where('evaluation_date_id', $date->id))
            ->update(['due_date' => $to]);
    }

    /** @return Collection<string, SubmissionDate> BR-FE-25 — kunci (jenis, siklus) unik per periode. */
    private function datesByKey(SubmissionPeriod $period): Collection
    {
        return $period->submissionDates()
            ->get()
            ->keyBy(fn (SubmissionDate $d): string => PeriodDateInput::keyOf($d->type, $d->cycle));
    }

    /** @param  list<int>  $keptIds */
    private function deleteUnlisted(SubmissionPeriod $period, array $keptIds): void
    {
        // Tanggal yang tidak lagi disebut dihapus — kecuali masih ditunjuk sebuah
        // FormPhaseDetail lewat kolom tenggat mana pun (BR-FE-26): ia masih menggerbangi fase.
        $period->submissionDates()
            ->whereNotIn('id', $keptIds)
            ->withoutPhaseDetail()
            ->delete();
    }
}
