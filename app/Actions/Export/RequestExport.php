<?php

namespace App\Actions\Export;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportType;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-02, BR-RPT-10 — permintaan ekspor.
 *
 * @method static ExportJob run(User $user, string $type)
 */
class RequestExport
{
    use AsAction;

    public function handle(User $user, string $type): ExportJob
    {
        ExportType::assertSupported($type);

        // Ambangnya dihitung dari **jumlah baris yang akan benar-benar diekspor**, bukan
        // dari seluruh isi tabel: batasnya sudah ter-scope (BR-RPT-10), jadi peneliti
        // dengan tiga pengajuan tidak masuk background job karena sistem punya sepuluh ribu.
        $rowCount = ExportType::query($type, $user)->count();

        $job = ExportJob::create([
            'requested_by' => $user->id,
            'type' => $type,
            'status' => ExportJob::STATUS_QUEUED,
            'row_count' => $rowCount,
        ]);

        AuditTrail::record('Ekspor diminta', $job, $user, new AuditChange(new: [
            'type' => $type,
            'row_count' => $rowCount,
        ]));

        // Yang besar dijadwalkan supaya Operator tidak menunggu di layar sama sekali; yang
        // kecil dijalankan langsung supaya ia tidak menunggu notifikasi untuk berkas sekejap.
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
