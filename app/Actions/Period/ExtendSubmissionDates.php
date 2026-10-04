<?php

namespace App\Actions\Period;

use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Support\Audit\AuditTrail;
use App\Support\Notification\Deliver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-08 — memperbarui tanggal periode, dan memberi tahu pemilik draft
 * kalau salah satu batasnya **mundur**; memajukan atau mengganti nama tidak
 * memberi peneliti kesempatan baru, memperpanjang memberi.
 *
 * @method static int run(SubmissionPeriod $period, array<int, array{label: string, date: string}> $dates)
 */
class ExtendSubmissionDates
{
    use AsAction;

    /**
     * @param  array<int, array{label: string, date: string}>  $dates
     * @return int jumlah peneliti yang diberi tahu
     */
    public function handle(SubmissionPeriod $period, array $dates): int
    {
        return DB::transaction(function () use ($period, $dates): int {
            // Mengirim untuk setiap penyuntingan membuat notifikasi ini cepat
            // diabaikan, dan yang hilang justru yang penting.
            if (!$this->syncDates($period, $dates)) {
                return 0;
            }

            $pemilikDraft = $this->draftOwners($period);

            // SCH-RULE-13 — tanpa draft berjalan, tidak ada yang perlu diberi tahu.
            if ($pemilikDraft->isEmpty()) {
                return 0;
            }

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record('Tenggat periode diperpanjang', $period);

            // Pemanggil yang membuka transaksi luar (ReviseSubmissionPeriod) bisa
            // menggulung semuanya; mengabari peneliti sebelum commit berarti
            // mengabari perpanjangan yang tak pernah terjadi.
            DB::afterCommit(static function () use ($pemilikDraft, $period): void {
                Deliver::to($pemilikDraft, new SubmissionDeadlineExtendedNotification($period));
            });

            return $pemilikDraft->count();
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
            $lama = $existing->get($row['label']);

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
                'submission_date_label_id' => SubmissionDateLabel::where('name', $row['label'])->firstOrFail()->id,
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

    /**
     * Draft **aktif** di periode itu: sudah dimulai tapi belum dikirim.
     * Pengajuan yang sudah masuk tidak terdampak batas pengisian.
     *
     * @return Collection<int, User>
     */
    private function draftOwners(SubmissionPeriod $period): Collection
    {
        return User::query()
            ->whereIn('id', FormSubmission::query()
                ->where('submission_period_id', $period->id)
                ->where('is_submitted', false)
                ->select('submitted_by'))
            ->get();
    }
}
