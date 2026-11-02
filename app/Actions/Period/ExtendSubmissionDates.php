<?php

namespace App\Actions\Period;

use App\Data\Result\DeadlineExtension;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Queries\Period\PeriodDraftOwners;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-08 — memberi tahu pemilik draft kalau batas bergeser LEBIH LAMA (nilai
 * lama dibandingkan dengan baru); memajukan atau mengganti nama tidak memberi
 * peneliti kesempatan baru, memperpanjang memberi.
 *
 * @method static DeadlineExtension run(SubmissionPeriod $period, array<int, array{label: string, date: string}> $dates, ?User $actor = null)
 */
class ExtendSubmissionDates
{
    use AsAction;

    /**
     * @param  array<int, array{label: string, date: string}>  $dates
     */
    public function handle(SubmissionPeriod $period, array $dates, ?User $actor = null): DeadlineExtension
    {
        return DB::transaction(function () use ($period, $dates, $actor): DeadlineExtension {
            // Mengirim untuk setiap penyuntingan membuat notifikasi ini cepat
            // diabaikan, dan yang hilang justru yang penting.
            if (!$this->syncDates($period, $dates)) {
                return new DeadlineExtension(extended: false, notified: 0);
            }

            // W73 — draft di periode yang ditutup Operator terarsip (BR-SM-13) dan tetap
            // terkunci (BR-SCH-07): bukan DRAFT aktif, jadi tidak ada janji yang dikirim.
            if ($period->is_force_closed) {
                return new DeadlineExtension(extended: true, notified: 0);
            }

            $pemilikDraft = PeriodDraftOwners::run($period);

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
        });
    }

    /**
     * Menyelaraskan tanggal periode dengan daftar masukan.
     *
     * @param  array<int, array{label: string, date: string}>  $dates
     * @return bool apakah ada batas yang mundur
     */
    private function syncDates(SubmissionPeriod $period, array $dates): bool
    {
        $existing = $this->datesByLabel($period);

        $adaPerpanjangan = false;
        $terpakai = [];

        foreach ($dates as $row) {
            $baru = Carbon::parse($row['date']);
            $lama = $existing->get(trim($row['label']));

            if ($lama instanceof SubmissionDate) {
                if ($baru->gt($lama->datetime)) {
                    $adaPerpanjangan = true;
                }

                // Hapus-lalu-buat-ulang gagal di periode terpakai: foreign key
                // `form_phase_details.submission_date_id` menunjuknya, dan bila
                // lolos, tiap FormPhaseDetail kehilangan tanggalnya.
                $lama->update(['datetime' => $baru]);
                $terpakai[] = $lama->id;

                continue;
            }

            $terpakai[] = SubmissionDate::create([
                'submission_period_id' => $period->id,
                'submission_date_label_id' => SubmissionDateLabel::named($row['label'])->id,
                'datetime' => $baru,
            ])->id;
        }

        $this->deleteUnlisted($period, $terpakai);

        return $adaPerpanjangan;
    }

    /** @return Collection<string, SubmissionDate> */
    private function datesByLabel(SubmissionPeriod $period): Collection
    {
        return $period->submissionDates()
            ->with('submissionDateLabel')
            ->get()
            ->keyBy(function (SubmissionDate $d): string {
                $label = $d->submissionDateLabel;

                return $label instanceof SubmissionDateLabel ? $label->name : (string) $d->id;
            });
    }

    /** @param  list<int>  $keptIds */
    private function deleteUnlisted(SubmissionPeriod $period, array $keptIds): void
    {
        // Tanggal yang tidak lagi disebut dihapus — kecuali masih ditunjuk
        // sebuah FormPhaseDetail, yang berarti ia masih menggerbangi fase.
        $period->submissionDates()
            ->whereNotIn('id', $keptIds)
            ->whereDoesntHave('formPhaseDetails')
            ->delete();
    }
}
