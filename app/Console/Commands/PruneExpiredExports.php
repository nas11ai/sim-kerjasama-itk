<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * BR-RPT-12 — berkas hasil ekspor dihapus otomatis setelah 24 jam.
 *
 * Barisnya **tidak** ikut dihapus: dari riwayat, Operator tahu ekspornya
 * pernah jadi lalu kedaluwarsa — bukan permintaan yang tidak pernah diproses.
 */
class PruneExpiredExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Hapus berkas hasil ekspor yang sudah lewat '.ExportRetention::HOURS.' jam (BR-RPT-12)';

    public function handle(): int
    {
        $dihapus = 0;

        foreach (ExportJob::query()->dueForCleanup()->cursor() as $job) {
            $path = (string) $job->file_path;

            if (Storage::disk(ExportWriter::DISK)->exists($path)) {
                Storage::disk(ExportWriter::DISK)->delete($path);
            }

            $job->update(['file_deleted_at' => now()]);

            AuditTrail::record(
                'Berkas ekspor dihapus setelah masa simpan',
                $job,
                null,
                new AuditChange(
                    new: [
                        'retention_hours' => ExportRetention::HOURS,
                    ],
                ),
            );

            $dihapus++;
        }

        $this->info("{$dihapus} berkas ekspor dihapus.");

        return self::SUCCESS;
    }
}
