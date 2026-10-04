<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ExportJob;
use App\Models\User;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportType;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-03 — riwayat ekspor milik satu user (25 terbaru) untuk layar Ekspor.
 *
 * @phpstan-type HistoryRow array{id: int, type: string, type_value: string, status: string, row_count: int, attempts: int, failure_reason: string|null, completed_at: string|null, file_is_gone: bool, download_url: string|null}
 *
 * @method static array<int, HistoryRow> run(User $user)
 */
class ExportHistory implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 25;

    /** @return array<int, HistoryRow> */
    public function handle(User $user): array
    {
        return ExportJob::query()
            ->where('requested_by', $user->id)
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ExportJob $job) => $this->row($job))
            ->all();
    }

    /** @return HistoryRow */
    private function row(ExportJob $job): array
    {
        return [
            'id' => $job->id,
            // Label untuk dibaca, nilai mentah untuk dikirim ulang: tombol
            // "ekspor ulang" yang mengirim label ditolak validasi, dan itu
            // baru ketahuan saat ditekan.
            'type' => ExportType::label($job->type),
            'type_value' => $job->type,
            'status' => $job->status,
            'row_count' => $job->row_count,
            'attempts' => $job->attempts,
            'failure_reason' => $job->failure_reason,
            'completed_at' => $job->completed_at?->toIso8601String(),
            'file_is_gone' => $job->fileIsGone(),
            'download_url' => $this->downloadUrl($job),
        ];
    }

    // BR-RPT-03 — tautan bertanda tangan dibuat saat layar dirender, bukan
    // disimpan: masa berlakunya baru mulai saat seseorang hendak mengunduh,
    // bukan sejak berkasnya jadi.
    private function downloadUrl(ExportJob $job): ?string
    {
        if ($job->status !== ExportJob::STATUS_COMPLETED || $job->fileIsGone()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'exports.download',
            now()->addHours(ExportRetention::SIGNED_URL_HOURS),
            ['export' => $job->id],
        );
    }
}
