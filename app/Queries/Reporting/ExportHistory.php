<?php

namespace App\Queries\Reporting;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Reporting\ExportFilters;
use App\Models\ExportJob;
use App\Models\User;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportType;
use App\Support\Reporting\PrintableDocument;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-03 — riwayat ekspor milik satu user (25 terbaru) untuk layar Ekspor. Baris cetak
 * PDF ikut tabel yang sama (BR-RPT-11) dan ditandai `kind: print`.
 *
 * @phpstan-type HistoryRow array{id: int, kind: 'export'|'print', type: string, type_value: string, format: string, filters: array<string, int|string>, parameters: string, status: string, row_count: int, attempts: int, failure_reason: string|null, requested_at: string|null, completed_at: string|null, file_is_gone: bool, download_url: string|null, link_expires_at: string|null, submission_href: string|null}
 *
 * @method static list<HistoryRow> run(User $user)
 */
class ExportHistory implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 25;

    /** @return list<HistoryRow> */
    public function handle(User $user): array
    {
        $jobs = ExportJob::query()->where('requested_by', $user->id)->latest('id')->limit(self::LIMIT)->get();
        $parameters = new ExportParameters(array_values(array_filter(
            $jobs->map(fn (ExportJob $job): ?int => ExportFilters::fromStored($job->filters)->scheme)->all(),
        )));

        return array_values($jobs->map(fn (ExportJob $job) => $this->row($job, $user, $parameters))->all());
    }

    /** @return HistoryRow */
    private function row(ExportJob $job, User $user, ExportParameters $parameters): array
    {
        $print = in_array($job->type, PrintableDocument::all(), true);
        $download = $this->downloadUrl($job);

        return [
            'id' => $job->id,
            'kind' => $print ? 'print' : 'export',
            // Label untuk dibaca, nilai mentah untuk dikirim ulang: tombol "ekspor ulang"
            // yang mengirim label ditolak validasi, dan itu baru ketahuan saat ditekan.
            'type' => ExportType::label($job->type),
            'type_value' => $job->type,
            'format' => $job->format,
            'filters' => $print ? [] : ($job->filters ?? []),
            'parameters' => $print ? $this->printSubject($job) : $parameters->describe($job, ExportFilters::fromStored($job->filters)),
            'status' => $job->status,
            'row_count' => $job->row_count,
            'attempts' => $job->attempts,
            'failure_reason' => $job->failure_reason,
            'requested_at' => $job->created_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'file_is_gone' => $job->fileIsGone(),
            'download_url' => $download,
            'link_expires_at' => $download === null ? null : now()->addHours(ExportRetention::SIGNED_URL_HOURS)->toIso8601String(),
            'submission_href' => $print ? $this->submissionHref($job, $user) : null,
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
            'reporting.exports.download',
            now()->addHours(ExportRetention::SIGNED_URL_HOURS),
            ['export' => $job->id],
        );
    }

    private function printSubject(ExportJob $job): string
    {
        return 'Pengajuan #'.($job->filters['form_submission_id'] ?? '?');
    }

    // Cetak ulang dilakukan dari halaman pengajuan; detail Operator butuh view-all (BR-SM-07).
    private function submissionHref(ExportJob $job, User $user): ?string
    {
        $id = $job->filters['form_submission_id'] ?? null;

        if ($id === null) {
            return null;
        }

        return SubmissionVisibility::seesEverything($user)
            ? route('admin.submissions.show', $id)
            : route('user.submissions.show', $id);
    }
}
