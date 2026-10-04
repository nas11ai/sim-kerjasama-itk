<?php

namespace App\Http\Controllers\Export;

use App\Actions\Export\RequestExport;
use App\Http\Controllers\Controller;
use App\Models\ExportJob;
use App\Queries\Reporting\ExportHistory;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\ExportType;
use App\Support\Reporting\ExportWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BR-RPT-02, BR-RPT-03, BR-RPT-10, BR-RPT-12 — ekspor data.
 *
 * Peneliti dan Operator memakai layar yang sama; yang membedakan mereka
 * adalah `SubmissionVisibility`, bukan controller masing-masing.
 */
class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return Inertia::render('Exports/IndexPage', [
            'types' => collect(ExportType::all())
                ->map(fn (string $type) => ['value' => $type, 'label' => ExportType::label($type)])
                ->all(),
            'threshold' => ExportThreshold::SYNCHRONOUS_ROWS,
            'retentionHours' => ExportRetention::HOURS,
            'jobs' => ExportHistory::run($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', ExportType::all())],
        ]);

        $job = RequestExport::run($user, $validated['type']);

        return back()->with(
            'success',
            $job->status === ExportJob::STATUS_COMPLETED
                ? 'Ekspor selesai — berkasnya siap diunduh.'
                : 'Ekspor dijadwalkan. Anda akan diberi tahu saat berkasnya siap.',
        );
    }

    /**
     * BR-RPT-03, BR-RPT-12 — dua penolakan berbeda, sengaja tidak disamakan:
     * tautan kedaluwarsa ditangani middleware `signed`; di sini berkas yang
     * sudah tidak ada, dan jalan keluarnya mengekspor ulang, bukan tautan baru.
     */
    public function download(Request $request, ExportJob $export): StreamedResponse
    {
        $user = $request->user();

        // BR-RPT-03 — signed URL saja tidak cukup: tautan bocor tetap tak
        // boleh membuka berkas milik orang lain.
        abort_unless($user !== null && $export->requested_by === $user->id, 403);

        abort_if(
            $export->fileIsGone() || $export->fileIsExpired(),
            410,
            'Berkas ekspor sudah dihapus setelah '.ExportRetention::HOURS.' jam. Jalankan ekspor ulang.',
        );

        $path = (string) $export->file_path;

        abort_unless(Storage::disk(ExportWriter::DISK)->exists($path), 410,
            'Berkas ekspor sudah tidak ada di penyimpanan. Jalankan ekspor ulang.');

        return Storage::disk(ExportWriter::DISK)->download($path, basename($path));
    }
}
