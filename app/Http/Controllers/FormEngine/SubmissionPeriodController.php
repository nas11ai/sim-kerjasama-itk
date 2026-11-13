<?php

namespace App\Http\Controllers\FormEngine;

use App\Actions\Period\CloseSubmissionPeriod;
use App\Actions\Period\CreateSubmissionPeriod;
use App\Actions\Period\DeleteSubmissionPeriod;
use App\Actions\Period\ReviseSubmissionPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Period\SubmissionPeriodRequest;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Queries\Period\SubmissionPeriodList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periode pengajuan (OPERATOR-09-01). Route dijaga `can:periods.manage` (§4.4 K1).
 */
class SubmissionPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'status' => $request->string('status')->value(),
            'year' => $request->string('year')->value(),
        ];

        return Inertia::render('FormEngine/PeriodIndexPage', [
            'filters' => $filters,
            ...SubmissionPeriodList::run($filters),
            // W69 — "Pengajuan Masuk" menaut ke Semua Pengajuan hanya bagi yang bisa membukanya.
            'can' => ['viewSubmissions' => $viewer->can('submissions.view-all')],
        ]);
    }

    public function store(SubmissionPeriodRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        CreateSubmissionPeriod::run($request->toInput(), $actor);

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengajuan dibuat.');
    }

    public function update(SubmissionPeriodRequest $request, SubmissionPeriod $submissionPeriod): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $extension = ReviseSubmissionPeriod::run($submissionPeriod, $request->toInput(), $actor);

        // BR-SCH-08 / SCH-RULE-13 — hanya bila ada tenggat yang mundur; 0 tetap dikirim
        // supaya Operator tahu tidak ada peneliti yang perlu diberi tahu (W73).
        if ($extension->extended) {
            Inertia::flash('deadlineExtended', $extension->notified);
        }

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengajuan diperbarui.');
    }

    // BR-FE-21 — penjaga rujukan berjalan di Action sebelum tanggal dan tahapnya disentuh.
    public function destroy(Request $request, SubmissionPeriod $submissionPeriod): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DeleteSubmissionPeriod::run($submissionPeriod, $actor);

        return redirect()->route('admin.submission-periods.index')
            ->with('success', 'Periode pengajuan dihapus.');
    }

    // Close Early (W68) — BR-SCH-07 mengunci fasenya, BR-SM-10 memberi tahu pemilik draft.
    public function close(Request $request, SubmissionPeriod $submissionPeriod): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $notified = CloseSubmissionPeriod::run($submissionPeriod, $actor);

        return back()->with('success', $notified > 0
            ? "Periode ditutup. {$notified} peneliti diberi tahu."
            : 'Periode ditutup. Tidak ada draf berjalan.');
    }
}
