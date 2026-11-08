<?php

namespace App\Actions\Export;

use App\Data\Reporting\ExportRequestData;
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
 * @method static ExportJob run(User $user, ExportRequestData $request)
 */
class RequestExport
{
    use AsAction;

    public function handle(User $user, ExportRequestData $request): ExportJob
    {
        $definition = ExportType::definition($request->type);
        // W154 — filter yang tidak berlaku bagi jenisnya dibuang, bukan diam-diam diabaikan.
        $filters = $request->filters->restrictedTo($definition->filterKeys());

        // Ambangnya dihitung dari **jumlah baris yang akan benar-benar diekspor**, bukan
        // dari seluruh isi tabel: batas dan filternya sudah diterapkan (BR-RPT-10,
        // [RPT-EXPORT-13]), jadi peneliti dengan tiga pengajuan tidak masuk background job.
        $rowCount = $definition->rowCount($user, $filters);

        $job = ExportJob::create([
            'requested_by' => $user->id,
            'type' => $request->type,
            'format' => $request->format->value,
            'filters' => $filters->toStored(),
            'status' => ExportJob::STATUS_QUEUED,
            'row_count' => $rowCount,
        ]);

        AuditTrail::record('Ekspor diminta', $job, $user, new AuditChange(new: [
            'type' => $request->type,
            'format' => $request->format->value,
            'filters' => $filters->toStored(),
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
