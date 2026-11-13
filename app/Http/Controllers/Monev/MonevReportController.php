<?php

namespace App\Http\Controllers\Monev;

use App\Actions\Monev\SaveMonevReport;
use App\Actions\Monev\SubmitMonevReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Monev\MonevReportRequest;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Monev\MonevCycles;
use App\Queries\Monev\MonevReportDetail;
use App\Queries\Monev\MonevReportForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Isi dan detail laporan monev (RESEARCHER-05-02/03). Penegakannya di SaveMonevReport/SubmitMonevReport;
 * controller hanya memilih halaman.
 */
class MonevReportController extends Controller
{
    public function edit(Request $request, FormSubmission $report): Response|RedirectResponse
    {
        $viewer = $this->viewer($request, $report);

        // MON-CYCLE-06 — laporan yang sudah dikirim dibuka, bukan disunting; anggota membaca saja.
        if ($report->is_submitted || $report->submitted_by !== $viewer->id) {
            return redirect()->route('monev.reports.show', $report);
        }

        return Inertia::render('Monev/ReportFormPage', MonevReportForm::run($viewer, $report));
    }

    public function save(MonevReportRequest $request, FormSubmission $report): RedirectResponse
    {
        SaveMonevReport::run($report, $request->toInput(), $this->viewer($request, $report));

        return back()->with('success', 'Draf laporan disimpan.');
    }

    public function submit(MonevReportRequest $request, FormSubmission $report): RedirectResponse
    {
        $actor = $this->viewer($request, $report);

        SaveMonevReport::run($report, $request->toInput(), $actor);
        SubmitMonevReport::run($report->refresh(), $actor);

        $name = $report->formPhaseDetail === null ? 'monev' : MonevCycles::nameOf($report->formPhaseDetail);

        // W132 — frame menaut ke detail laporan, bukan ke Daftar Monev.
        return redirect()->route('monev.reports.show', $report)->with('success', "Laporan {$name} dikirim.");
    }

    public function show(Request $request, FormSubmission $report): Response
    {
        $viewer = $this->viewer($request, $report);

        return Inertia::render('Monev/ReportShowPage', MonevReportDetail::run($viewer, $report));
    }

    /** BR-SM-07 — hanya pihak yang boleh membaca laporan; bukan laporan anak → 404. */
    private function viewer(Request $request, FormSubmission $report): User
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $viewer->can('view', $report), 403);
        abort_if($report->parent_submission_id === null, 404);

        return $viewer;
    }
}
