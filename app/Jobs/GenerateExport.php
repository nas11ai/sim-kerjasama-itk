<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\User;
use App\Notifications\ExportCompletedNotification;
use App\Notifications\ExportFailedNotification;
use App\Support\Notification\Deliver;
use App\Support\Reporting\ExportWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * BR-RPT-02, BR-RPT-07, BR-RPT-08 — pembuatan berkas ekspor.
 *
 * Dipakai dua jalur: `dispatch()` untuk ekspor besar dan `dispatchSync()`
 * untuk yang kecil. Satu kelas, supaya berkas yang dihasilkan keduanya
 * identik.
 *
 * Tiga percobaan dengan jeda menaik, lalu **berhenti**. Percobaan yang tidak
 * berujung hanya menyembunyikan kegagalan yang perlu dilihat orang, dan
 * Operator yang tidak pernah diberi tahu tidak akan pernah mengulang
 * ekspornya.
 */
class GenerateExport implements ShouldQueue
{
    use Queueable;

    /** BR-RPT-08 — maksimal tiga percobaan. */
    public int $tries = 3;

    public function __construct(private readonly int $exportJobId) {}

    /**
     * Exponential backoff: 10 detik, lalu 40, lalu menyerah. Kegagalan yang
     * disebabkan beban sesaat sempat pulih; yang disebabkan cacat data tidak
     * akan pulih berapa kali pun diulang, jadi tidak ada gunanya menunggu
     * lebih lama.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 40];
    }

    public function handle(): void
    {
        $job = ExportJob::query()->find($this->exportJobId);

        if (!$job instanceof ExportJob) {
            return;
        }

        $user = $job->requestedBy;

        if (!$user instanceof User) {
            throw new RuntimeException("ExportJob {$job->id} tidak punya pemohon — tidak bisa di-scope.");
        }

        $job->update([
            'status' => ExportJob::STATUS_PROCESSING,
            'attempts' => $job->attempts + 1,
        ]);

        $path = ExportWriter::write($job, $user);

        $job->update([
            'status' => ExportJob::STATUS_COMPLETED,
            'file_path' => $path,
            'completed_at' => now(),
            'failure_reason' => null,
        ]);

        // BR-RPT-07 — yang diberi tahu adalah yang meminta, bukan semua
        // Operator: yang lain tidak sedang menunggu berkas ini.
        Deliver::to($user, new ExportCompletedNotification($job->refresh()), [
            'export_job_id' => $job->id,
        ]);
    }

    /**
     * BR-RPT-08 — dipanggil setelah percobaan terakhir habis.
     */
    public function failed(Throwable $exception): void
    {
        $job = ExportJob::query()->find($this->exportJobId);

        if (!$job instanceof ExportJob) {
            return;
        }

        $job->update([
            'status' => ExportJob::STATUS_FAILED,
            'failure_reason' => $exception->getMessage(),
        ]);

        $user = $job->requestedBy;

        if ($user instanceof User) {
            Deliver::to($user, new ExportFailedNotification($job->refresh()), [
                'export_job_id' => $job->id,
            ]);
        }
    }
}
