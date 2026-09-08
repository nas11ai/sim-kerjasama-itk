<?php

namespace App\Actions\Period;

use App\Models\FormSubmission;
use App\Models\SubmissionDate;
use App\Models\SubmissionDateLabel;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Notifications\SubmissionDeadlineExtendedNotification;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SCH-08 — memperbarui tanggal periode, dan memberi tahu pemilik draft
 * kalau salah satu batasnya **mundur**.
 *
 * Yang memicu pemberitahuan adalah perpanjangan, bukan setiap penyuntingan.
 * Memajukan tanggal atau mengganti namanya tidak memberi peneliti kesempatan
 * baru; memperpanjangnya memberi. Mengirim untuk keduanya membuat notifikasi
 * ini cepat diabaikan, dan yang hilang justru yang penting.
 *
 * Draft yang dihitung adalah draft **aktif** di periode itu — sudah dimulai
 * tapi belum dikirim. Pengajuan yang sudah masuk tidak terdampak batas
 * pengisian.
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
        $adaPerpanjangan = false;

        DB::transaction(function () use ($period, $dates, &$adaPerpanjangan): void {
            $existing = $period->submissionDates()
                ->with('submissionDateLabel')
                ->get()
                ->keyBy(function (SubmissionDate $d): string {
                    $label = $d->submissionDateLabel;

                    return $label instanceof SubmissionDateLabel ? $label->name : (string) $d->id;
                });

            $terpakai = [];

            foreach ($dates as $row) {
                $baru = Carbon::parse($row['date']);
                $lama = $existing->get($row['label']);

                if ($lama instanceof SubmissionDate) {
                    if ($baru->gt($lama->datetime)) {
                        $adaPerpanjangan = true;
                    }

                    // Diperbarui **di tempat**, bukan dihapus lalu dibuat ulang.
                    // `form_phase_details.submission_date_id` menunjuk ke baris
                    // ini lewat foreign key: menghapusnya membuat penyuntingan
                    // periode yang sudah dipakai selalu gagal, dan kalaupun
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

            // Tanggal yang tidak lagi disebut dihapus — kecuali masih ditunjuk
            // sebuah FormPhaseDetail, yang berarti ia masih menggerbangi fase.
            $period->submissionDates()
                ->whereNotIn('id', $terpakai)
                ->whereDoesntHave('formPhaseDetails')
                ->delete();
        });

        if (!$adaPerpanjangan) {
            return 0;
        }

        $pemilikDraft = User::query()
            ->whereIn('id', FormSubmission::query()
                ->where('submission_period_id', $period->id)
                ->where('is_submitted', false)
                ->select('submitted_by'))
            ->get();

        // SCH-RULE-13 — tanpa draft berjalan, tidak ada yang perlu diberi tahu.
        if ($pemilikDraft->isEmpty()) {
            return 0;
        }

        Notification::send($pemilikDraft, new SubmissionDeadlineExtendedNotification($period));

        $tercatat = $pemilikDraft->count();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Tenggat periode diperpanjang', $period);

        return $tercatat;
    }
}
