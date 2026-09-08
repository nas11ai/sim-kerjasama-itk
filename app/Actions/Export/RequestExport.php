<?php

namespace App\Actions\Export;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportType;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-02, BR-RPT-10 — permintaan ekspor.
 *
 * Ambangnya dihitung dari **jumlah baris yang akan benar-benar diekspor**,
 * bukan dari jumlah seluruh baris di tabel: batasnya sudah ter-scope
 * (BR-RPT-10), jadi peneliti dengan tiga pengajuan tidak pernah masuk
 * background job hanya karena sistemnya punya sepuluh ribu.
 *
 * Yang kecil dijalankan langsung supaya Operator tidak menunggu notifikasi
 * untuk berkas yang siap dalam sekejap; yang besar dijadwalkan supaya ia
 * tidak menunggu di layar sama sekali.
 *
 * @method static ExportJob run(User $user, string $type, array<string, mixed> $filters = [])
 */
class RequestExport
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(User $user, string $type, array $filters = []): ExportJob
    {
        ExportType::assertSupported($type);

        $rowCount = ExportType::query($type, $user)->count();

        $job = ExportJob::create([
            'requested_by' => $user->id,
            'type' => $type,
            'filters' => $filters === [] ? null : $filters,
            'status' => ExportJob::STATUS_QUEUED,
            'row_count' => $rowCount,
        ]);

        AuditTrail::record('Ekspor diminta', $job, $user, null, [
            'type' => $type,
            'row_count' => $rowCount,
        ]);

        if (ExportThreshold::needsBackgroundJob($rowCount)) {
            GenerateExport::dispatch($job->id);

            return $job;
        }

        // Jalur sinkron memakai Job yang sama, bukan salinan logikanya:
        // dua penulis untuk dua jalur akan berbeda isinya pada kolom pertama
        // yang ditambahkan ke salah satunya.
        GenerateExport::dispatchSync($job->id);

        return $job->refresh();
    }
}
